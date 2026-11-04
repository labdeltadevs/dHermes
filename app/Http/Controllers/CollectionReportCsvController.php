<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Queries\CollectionQuery;
use App\Support\Collection\CollectionReportFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CollectionReportCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $filters = CollectionReportFilters::fromRequest($request);
        $events = app(CollectionQuery::class)->filtered($user, $filters)->get();

        $filename = 'cobranzas_'.$filters['from'].'_a_'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($events): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM para que Excel abra el archivo en UTF-8.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Identificador app',
                'Identificador interno (UUID)',
                'Factura (NumAtCard)',
                'Monto asignado',
                'Moneda',
                'Fecha/hora creación',
                'Código cliente',
                'Cliente',
                'Estado',
            ]);

            foreach (PaymentMethod::cases() as $method) {
                $group = $events->where('payment_method', $method)->values();

                if ($group->isEmpty()) {
                    continue;
                }

                fputcsv($handle, ['MÉTODO: '.mb_strtoupper($method->label())]);

                foreach ($group as $event) {
                    $details = $this->invoiceDetails($event);

                    fputcsv($handle, [
                        $event->number_code ?? '',
                        $event->id,
                        $details['invoice'],
                        number_format((float) $event->amount, 2, '.', ''),
                        $event->currency,
                        $event->created_at?->format('d/m/Y H:i') ?? '',
                        $details['cardcode'],
                        $details['customer'],
                        $event->status->label(),
                    ]);
                }

                foreach ($group->groupBy('currency') as $currency => $rows) {
                    fputcsv($handle, [
                        'Subtotal '.$method->label().' ('.$currency.')',
                        '',
                        '',
                        number_format((float) $rows->sum(fn (CollectionEvent $event): float => (float) $event->amount), 2, '.', ''),
                        $currency,
                        $rows->count().' cobros',
                        '',
                        '',
                        '',
                    ]);
                }
            }

            if ($events->isNotEmpty()) {
                fputcsv($handle, ['']);

                foreach ($events->groupBy('currency') as $currency => $rows) {
                    fputcsv($handle, [
                        'TOTAL GENERAL ('.$currency.')',
                        '',
                        '',
                        number_format((float) $rows->sum(fn (CollectionEvent $event): float => (float) $event->amount), 2, '.', ''),
                        $currency,
                        $rows->count().' cobros',
                        '',
                        '',
                        '',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{invoice: string, cardcode: string, customer: string}
     */
    private function invoiceDetails(CollectionEvent $event): array
    {
        $invoice = $event->getRelation('invoice');

        if ($invoice instanceof Invoice) {
            $customer = $invoice->getRelation('customer');

            return [
                'invoice' => (string) $invoice->number,
                'cardcode' => $customer instanceof Customer ? (string) $customer->cardcode : '',
                'customer' => $customer instanceof Customer ? (string) $customer->name : '',
            ];
        }

        return [
            'invoice' => (string) ($event->flash_invoice_number ?? ''),
            'cardcode' => (string) ($event->flash_cardcode ?? ''),
            'customer' => (string) ($event->flash_customer_name ?? ''),
        ];
    }
}
