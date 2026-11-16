<?php

namespace App\Support\Collection;

use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
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
        ?string $generatedByName,
    ): string {
        $html = $this->generateHtml(
            $events,
            $filters,
            $tenantName,
            $sellerName,
            $statusLabel,
            $paymentMethodLabel,
            $generatedByName,
        );

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        // Oficio/folio: 8.5 x 13 pulgadas (612 x 936 puntos), vertical.
        $dompdf->setPaper([0.0, 0.0, 612.0, 936.0]);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @param  Collection<int, CollectionEvent>  $events
     * @param  array{from: string, to: string, status: string, payment_method: string, seller_user_id: string, currency: string}  $filters
     */
    public function generateHtml(
        Collection $events,
        array $filters,
        ?string $tenantName,
        ?string $sellerName,
        ?string $statusLabel,
        ?string $paymentMethodLabel,
        ?string $generatedByName,
    ): string {
        $invoiceNumbers = $this->invoiceNumbersByEvent($events);

        $rows = $events
            ->map(fn (CollectionEvent $event): array => $this->row($event, $invoiceNumbers[$event->id] ?? ''))
            ->values();

        $arqueo = $rows
            ->groupBy('currency')
            ->map(fn (Collection $group, string $currency): array => [
                'currency' => $currency,
                'total' => (float) $group->sum('amount'),
                'count' => $group->count(),
                'methods' => collect(PaymentMethod::cases())
                    ->map(function (PaymentMethod $method) use ($group): array {
                        $methodRows = $group->where('method', $method->value);

                        return [
                            'label' => $method->label(),
                            'total' => (float) $methodRows->sum('amount'),
                            'count' => $methodRows->count(),
                        ];
                    })
                    ->values(),
            ])
            ->values();

        $generatedAt = Carbon::now();

        return view('reports.collections-pdf', [
            'tenantName' => $tenantName,
            'filters' => $filters,
            'sellerName' => $sellerName,
            'statusLabel' => $statusLabel,
            'paymentMethodLabel' => $paymentMethodLabel,
            'generatedByName' => $generatedByName,
            'reportCode' => 'RCO-'.$generatedAt->format('Ymd-His'),
            'rows' => $rows,
            'arqueo' => $arqueo,
            'totalCount' => $rows->count(),
            'generatedAt' => $generatedAt,
        ])->render();
    }

    /**
     * Resuelve la lista de facturas (NumAtCard) de cada cobro:
     * - cobro flash: el texto ingresado por el vendedor (separado por comas);
     * - cobro conciliado: todas las facturas aplicadas al recibo del ERP;
     * - resto: la factura propia del cobro.
     *
     * @param  Collection<int, CollectionEvent>  $events
     * @return array<string, string>
     */
    private function invoiceNumbersByEvent(Collection $events): array
    {
        $numbers = [];

        foreach ($events as $event) {
            $flash = trim((string) ($event->flash_invoice_number ?? ''));

            if ($flash !== '') {
                $numbers[$event->id] = $this->normalizeInvoiceList($flash);
            }
        }

        $docnums = $events
            ->pluck('erp_docnum')
            ->filter(fn (mixed $docnum): bool => is_string($docnum) && trim($docnum) !== '')
            ->map(fn (string $docnum): string => trim($docnum))
            ->unique()
            ->values();

        if ($docnums->isNotEmpty()) {
            $receipts = Receipt::query()
                ->whereIn('number', $docnums->all())
                ->get(['id', 'number']);

            if ($receipts->isNotEmpty()) {
                $applications = ReceiptApplication::query()
                    ->with('invoice:id,number')
                    ->whereIn('receipt_id', $receipts->pluck('id')->all())
                    ->where('invtype', '13')
                    ->get();

                /** @var array<string, list<string>> $numbersByReceipt */
                $numbersByReceipt = [];

                foreach ($applications as $application) {
                    $invoice = $application->invoice;

                    if (! $invoice instanceof Invoice) {
                        continue;
                    }

                    $numbersByReceipt[$application->receipt_id][] = (string) $invoice->number;
                }

                /** @var array<string, list<string>> $numbersByDocnum */
                $numbersByDocnum = [];

                foreach ($receipts as $receipt) {
                    $numbersByDocnum[(string) $receipt->number] = $numbersByReceipt[$receipt->id] ?? [];
                }

                foreach ($events as $event) {
                    if (isset($numbers[$event->id])) {
                        continue;
                    }

                    $docnum = trim((string) ($event->erp_docnum ?? ''));

                    if ($docnum === '' || ! isset($numbersByDocnum[$docnum])) {
                        continue;
                    }

                    $list = collect($numbersByDocnum[$docnum]);
                    $ownInvoice = $event->getRelation('invoice');

                    if ($ownInvoice instanceof Invoice) {
                        $list->prepend((string) $ownInvoice->number);
                    }

                    $resolved = $list
                        ->map(fn (string $number): string => trim($number))
                        ->filter(fn (string $number): bool => $number !== '')
                        ->unique()
                        ->values();

                    if ($resolved->isNotEmpty()) {
                        $numbers[$event->id] = $resolved->implode(', ');
                    }
                }
            }
        }

        foreach ($events as $event) {
            if (isset($numbers[$event->id])) {
                continue;
            }

            $invoice = $event->getRelation('invoice');

            if ($invoice instanceof Invoice) {
                $numbers[$event->id] = (string) $invoice->number;
            }
        }

        return $numbers;
    }

    private function normalizeInvoiceList(string $value): string
    {
        return collect(explode(',', $value))
            ->map(fn (string $number): string => trim($number))
            ->filter(fn (string $number): bool => $number !== '')
            ->implode(', ');
    }

    /**
     * @return array{
     *     number_code: string,
     *     invoice: string,
     *     amount: float,
     *     currency: string,
     *     created_at: string,
     *     cardcode: string,
     *     customer: string,
     *     status: string,
     *     method: string,
     *     method_label: string
     * }
     */
    private function row(CollectionEvent $event, string $invoiceNumbers): array
    {
        $invoice = $event->getRelation('invoice');
        $cardcode = (string) ($event->flash_cardcode ?? '');
        $customer = (string) ($event->flash_customer_name ?? '');

        if ($invoice instanceof Invoice) {
            $invoiceCustomer = $invoice->getRelation('customer');

            if ($invoiceCustomer instanceof Customer) {
                $cardcode = (string) $invoiceCustomer->cardcode;
                $customer = (string) $invoiceCustomer->name;
            }
        }

        return [
            'number_code' => (string) ($event->number_code ?? ''),
            'invoice' => $invoiceNumbers,
            'amount' => (float) $event->amount,
            'currency' => (string) $event->currency,
            'created_at' => $event->created_at?->format('d/m/Y H:i') ?? '',
            'cardcode' => $cardcode,
            'customer' => $customer,
            'status' => $event->status->label(),
            'method' => $event->payment_method->value,
            'method_label' => $event->payment_method->label(),
        ];
    }
}
