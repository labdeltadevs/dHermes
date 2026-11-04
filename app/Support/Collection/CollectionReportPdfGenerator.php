<?php

namespace App\Support\Collection;

use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

final class CollectionReportPdfGenerator
{
    /**
     * @param  Collection<int, CollectionEvent>  $events
     * @param  array{from: string, to: string, status: string, payment_method: string, seller_user_id: string, currency: string}  $filters
     */
    public function generate(
        Collection $events,
        array $filters,
        ?string $tenantName,
        ?string $sellerName,
        ?string $statusLabel,
        ?string $paymentMethodLabel,
    ): string {
        $rows = $events->map(fn (CollectionEvent $event): array => $this->row($event))->values();

        $groups = collect(PaymentMethod::cases())
            ->map(function (PaymentMethod $method) use ($rows): array {
                $methodRows = $rows->where('method', $method->value)->values();

                return [
                    'method' => $method,
                    'rows' => $methodRows,
                    'subtotals' => $methodRows
                        ->groupBy('currency')
                        ->map(fn (Collection $group, string $currency): array => [
                            'currency' => $currency,
                            'total' => (float) $group->sum('amount'),
                            'count' => $group->count(),
                        ])
                        ->values(),
                ];
            })
            ->filter(fn (array $group): bool => $group['rows']->isNotEmpty())
            ->values();

        $totals = $rows->groupBy('currency')
            ->map(fn (Collection $group, string $currency): array => [
                'currency' => $currency,
                'total' => (float) $group->sum('amount'),
                'count' => $group->count(),
            ])
            ->values();

        $html = view('reports.collections-pdf', [
            'tenantName' => $tenantName,
            'filters' => $filters,
            'sellerName' => $sellerName,
            'statusLabel' => $statusLabel,
            'paymentMethodLabel' => $paymentMethodLabel,
            'groups' => $groups,
            'totals' => $totals,
            'totalCount' => $rows->count(),
            'generatedAt' => now(),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @return array{
     *     number_code: string,
     *     id: string,
     *     invoice: string,
     *     amount: float,
     *     currency: string,
     *     created_at: string,
     *     cardcode: string,
     *     customer: string,
     *     status: string,
     *     method: string
     * }
     */
    private function row(CollectionEvent $event): array
    {
        $invoice = $event->getRelation('invoice');
        $invoiceNumber = (string) ($event->flash_invoice_number ?? '');
        $cardcode = (string) ($event->flash_cardcode ?? '');
        $customer = (string) ($event->flash_customer_name ?? '');

        if ($invoice instanceof Invoice) {
            $invoiceCustomer = $invoice->getRelation('customer');

            $invoiceNumber = (string) $invoice->number;

            if ($invoiceCustomer instanceof Customer) {
                $cardcode = (string) $invoiceCustomer->cardcode;
                $customer = (string) $invoiceCustomer->name;
            }
        }

        return [
            'number_code' => (string) ($event->number_code ?? ''),
            'id' => (string) $event->id,
            'invoice' => $invoiceNumber,
            'amount' => (float) $event->amount,
            'currency' => (string) $event->currency,
            'created_at' => $event->created_at?->format('d/m/Y H:i') ?? '',
            'cardcode' => $cardcode,
            'customer' => $customer,
            'status' => $event->status->label(),
            'method' => $event->payment_method->value,
        ];
    }
}
