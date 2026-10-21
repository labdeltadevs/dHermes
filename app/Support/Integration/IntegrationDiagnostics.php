<?php

namespace App\Support\Integration;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class IntegrationDiagnostics
{
    /**
     * @return array<int, array{key: string, label: string, description: string, severity: string, count: int}>
     */
    public function run(?string $tenantId = null): array
    {
        return [
            $this->check(
                'invoice_balance',
                'Facturas con total distinto de pagado + saldo',
                'error',
                $this->invoices($tenantId)
                    ->where('cancelled', false)
                    ->whereRaw('ABS(total - paid - balance) > 0.01')
                    ->count(),
                'La suma pagado + saldo debe coincidir con el total de la factura.',
            ),
            $this->check(
                'invoice_negative_balance',
                'Facturas abiertas con saldo negativo',
                'error',
                $this->invoices($tenantId)
                    ->where('cancelled', false)
                    ->where('balance', '<', 0)
                    ->count(),
                'Una factura activa no deberia tener saldo pendiente negativo.',
            ),
            $this->check(
                'invoice_future_issue',
                'Facturas con fecha de emision futura',
                'warning',
                $this->invoices($tenantId)
                    ->where('issue_date', '>', now()->toDateString())
                    ->count(),
                'Revisa la zona horaria o el dato de origen en SAP.',
            ),
            $this->check(
                'invoice_cancelled_with_balance',
                'Facturas anuladas con saldo pendiente',
                'error',
                $this->invoices($tenantId)
                    ->where('cancelled', true)
                    ->whereRaw('ABS(balance) > 0.01')
                    ->count(),
                'Las facturas anuladas no deberian conservar saldo.',
            ),
            $this->check(
                'receipt_overapplied',
                'Comprobantes aplicados por encima de su total',
                'error',
                $this->receipts($tenantId)
                    ->whereRaw(
                        'total < (select coalesce(sum(receipt_applications.applied_amount), 0) from receipt_applications where receipt_applications.receipt_id = receipts.id and receipt_applications.tenant_id = receipts.tenant_id) - 0.01'
                    )
                    ->count(),
                'La suma aplicada de un comprobante no puede superar su importe.',
            ),
            $this->check(
                'receipt_application_invtype',
                'Aplicaciones con InvType distinto de 13',
                'error',
                $this->applications($tenantId)
                    ->where('invtype', '!=', '13')
                    ->count(),
                'El contrato RCT2 solo admite aplicaciones de facturas de cliente (InvType 13).',
            ),
            $this->check(
                'receipt_application_customer_mismatch',
                'Aplicaciones entre comprobante y factura de distinto cliente',
                'error',
                $this->applications($tenantId)
                    ->join('receipts', function ($join): void {
                        $join->on('receipts.id', '=', 'receipt_applications.receipt_id')
                            ->on('receipts.tenant_id', '=', 'receipt_applications.tenant_id');
                    })
                    ->join('invoices', function ($join): void {
                        $join->on('invoices.id', '=', 'receipt_applications.invoice_id')
                            ->on('invoices.tenant_id', '=', 'receipt_applications.tenant_id');
                    })
                    ->whereColumn('receipts.customer_id', '!=', 'invoices.customer_id')
                    ->count(),
                'Una aplicacion debe relacionar un comprobante y una factura del mismo cliente.',
            ),
            $this->check(
                'receipt_currency_mismatch',
                'Aplicaciones con monedas distintas entre comprobante y factura',
                'warning',
                $this->applications($tenantId)
                    ->join('receipts', function ($join): void {
                        $join->on('receipts.id', '=', 'receipt_applications.receipt_id')
                            ->on('receipts.tenant_id', '=', 'receipt_applications.tenant_id');
                    })
                    ->join('invoices', function ($join): void {
                        $join->on('invoices.id', '=', 'receipt_applications.invoice_id')
                            ->on('invoices.tenant_id', '=', 'receipt_applications.tenant_id');
                    })
                    ->whereColumn('receipts.currency', '!=', 'invoices.currency')
                    ->count(),
                'La conciliacion de cobros exige la misma moneda en comprobante y factura.',
            ),
            $this->check(
                'receipt_cancelled_with_applications',
                'Comprobantes anulados con aplicaciones',
                'warning',
                $this->receipts($tenantId)
                    ->where('cancelled', true)
                    ->whereExists(function (Builder $query): void {
                        $query->select(DB::raw(1))
                            ->from('receipt_applications')
                            ->whereColumn('receipt_applications.receipt_id', 'receipts.id')
                            ->whereColumn('receipt_applications.tenant_id', 'receipts.tenant_id');
                    })
                    ->count(),
                'Un comprobante anulado no deberia conservar aplicaciones activas.',
            ),
            $this->check(
                'collector_missing_membership',
                'Cobradores asignados sin membresia en la empresa',
                'warning',
                $this->customers($tenantId)
                    ->whereNotNull('collector_user_id')
                    ->whereNotExists(function (Builder $query): void {
                        $query->select(DB::raw(1))
                            ->from('tenant_users')
                            ->whereColumn('tenant_users.tenant_id', 'customers.tenant_id')
                            ->whereColumn('tenant_users.user_id', 'customers.collector_user_id');
                    })
                    ->count(),
                'Asigna el cobrador a la empresa o retira la asignacion del cliente.',
            ),
            $this->check(
                'customer_missing_collector',
                'Clientes sin cobrador asignado',
                'info',
                $this->customers($tenantId)->whereNull('collector_user_id')->count(),
                'Dato informativo para planificar la asignacion de cartera.',
            ),
            $this->check(
                'invoice_customer_rep_mismatch',
                'Facturas abiertas con vendedor distinto al del cliente',
                'warning',
                $this->invoices($tenantId)
                    ->join('customers', function ($join): void {
                        $join->on('customers.id', '=', 'invoices.customer_id')
                            ->on('customers.tenant_id', '=', 'invoices.tenant_id');
                    })
                    ->where('invoices.cancelled', false)
                    ->whereNotNull('invoices.sales_rep_id')
                    ->whereNotNull('customers.sales_rep_id')
                    ->whereColumn('invoices.sales_rep_id', '!=', 'customers.sales_rep_id')
                    ->count(),
                'La cartera se controla por el vendedor de la factura; revisa la consistencia en SAP.',
            ),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function lastSyncedAt(?string $tenantId = null): array
    {
        $tables = ['invoices', 'receipts', 'receipt_applications'];
        $result = [];

        foreach ($tables as $table) {
            $value = DB::table($table)
                ->when($tenantId !== null, fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))
                ->max('synced_at');

            $result[$table] = $value === null ? null : (string) $value;
        }

        return $result;
    }

    private function invoices(?string $tenantId): Builder
    {
        return DB::table('invoices')
            ->when($tenantId !== null, fn (Builder $query): Builder => $query->where('invoices.tenant_id', $tenantId));
    }

    private function receipts(?string $tenantId): Builder
    {
        return DB::table('receipts')
            ->when($tenantId !== null, fn (Builder $query): Builder => $query->where('receipts.tenant_id', $tenantId));
    }

    private function applications(?string $tenantId): Builder
    {
        return DB::table('receipt_applications')
            ->when($tenantId !== null, fn (Builder $query): Builder => $query->where('receipt_applications.tenant_id', $tenantId));
    }

    private function customers(?string $tenantId): Builder
    {
        return DB::table('customers')
            ->when($tenantId !== null, fn (Builder $query): Builder => $query->where('customers.tenant_id', $tenantId));
    }

    /**
     * @return array{key: string, label: string, description: string, severity: string, count: int}
     */
    private function check(string $key, string $label, string $severity, int $count, string $description): array
    {
        return compact('key', 'label', 'severity', 'count', 'description');
    }
}
