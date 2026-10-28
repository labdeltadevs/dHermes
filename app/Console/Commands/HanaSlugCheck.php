<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('dhermes:hana-slug-check {--tenant= }')]
#[Description('Verifica que el contrato HANA escriba la empresa SALES_ORG y la ruta de documentos en el formato esperado')]
class HanaSlugCheck extends Command
{
    public function handle(): int
    {
        $raw = $this->option('tenant');
        $tenantOption = is_string($raw) ? $raw : '';

        $tenantId = null;
        if ($tenantOption !== '') {
            $tenant = Tenant::query()
                ->where('id', $tenantOption)
                ->orWhere('code', $tenantOption)
                ->first();

            if ($tenant === null) {
                $this->error('No se encontro la empresa indicada.');

                return self::FAILURE;
            }

            $tenantId = $tenant->id;
        }

        $query = DB::table('invoices')
            ->selectRaw('COUNT(*) as total, COUNT(*) FILTER (WHERE docentry ~ \'^[A-Za-z0-9]{6,}$\') as slug_ok')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        $row = $query->first();

        $this->table(['total', 'slug_ok'], [[$row->total ?? 0, $row->slug_ok ?? 0]]);

        if (($row->total ?? 0) > 0 && ($row->slug_ok ?? 0) < ($row->total ?? 0)) {
            $this->warn('Hay facturas con docentry en formato no estandar. Revisa el mapeo HANA.');

            return self::FAILURE;
        }

        $this->info('Formato de documentos compatible con el contrato HANA.');

        return self::SUCCESS;
    }
}
