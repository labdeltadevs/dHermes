<?php

namespace App\Actions\Collection;

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Collection\CollectionProofStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RegisterCollection
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $seller, Invoice $invoice, array $data): CollectionEvent
    {
        abort_unless($invoice->tenant_id === $seller->currentTenantId(), 403);

        Gate::authorize('create', [CollectionEvent::class, $invoice]);

        $proof = $data['proof'] ?? null;

        $validated = Validator::make($data, [
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'local_amount' => ['nullable', 'numeric'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'collected_on' => ['required', 'date'],
            'kind' => ['required', Rule::enum(CollectionKind::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'voucher_code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('collection_events', 'voucher_code')
                    ->where(fn ($query) => $query->where('tenant_id', $invoice->tenant_id)),
            ],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ])->validate();

        $paymentMethod = $validated['payment_method'] instanceof PaymentMethod
            ? $validated['payment_method']
            : PaymentMethod::from((string) $validated['payment_method']);

        $voucherCode = trim((string) ($validated['voucher_code'] ?? ''));

        if ($paymentMethod !== PaymentMethod::Cash && $voucherCode === '') {
            throw ValidationException::withMessages([
                'voucher_code' => 'El código de voucher es obligatorio para este método de pago.',
            ]);
        }

        if ($paymentMethod === PaymentMethod::Cash && $voucherCode === '') {
            $voucherCode = null;
        }

        if ($validated['currency'] !== $invoice->currency) {
            throw ValidationException::withMessages([
                'currency' => 'La moneda del cobro debe coincidir con la moneda de la factura.',
            ]);
        }

        if (Carbon::parse($validated['collected_on'])->startOfDay()->isAfter(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'collected_on' => 'La fecha de cobro no puede ser posterior a hoy.',
            ]);
        }

        $amount = (float) $validated['amount'];
        $kind = $validated['kind'] instanceof CollectionKind
            ? $validated['kind']
            : CollectionKind::from((string) $validated['kind']);

        $this->assertAmountWithinAvailable($invoice, $amount, $kind);

        $storage = app(CollectionProofStorage::class);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($seller, $invoice, $validated, $amount, $kind, $voucherCode, $proof, $storage, &$storedPath): CollectionEvent {
                $invoice = Invoice::query()
                    ->whereKey($invoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAmountWithinAvailable($invoice, $amount, $kind);

                $attributes = $validated;
                unset($attributes['proof']);

                $collectionEvent = CollectionEvent::create([
                    ...$attributes,
                    'voucher_code' => $voucherCode,
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->id,
                    'seller_user_id' => $seller->id,
                    'status' => CollectionStatus::Pending,
                ]);

                if ($proof instanceof UploadedFile) {
                    $storedPath = $storage->store($collectionEvent, $proof);
                }

                app(AuditLogger::class)->record(
                    $invoice->tenant_id,
                    'collection.created',
                    CollectionEvent::class,
                    $collectionEvent->id,
                    [
                        'invoice_id' => $invoice->id,
                        'amount' => $collectionEvent->amount,
                        'currency' => $collectionEvent->currency,
                        'payment_method' => $collectionEvent->payment_method->value,
                        'voucher_code' => $collectionEvent->voucher_code,
                        'has_proof' => $collectionEvent->proof_path !== null,
                    ],
                    $seller,
                );

                return $collectionEvent;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk(CollectionProofStorage::DISK)->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Valida que el monto no supere el saldo disponible: saldo de la factura
     * menos el total de cobros aún pendientes sobre ella.
     */
    private function assertAmountWithinAvailable(Invoice $invoice, float $amount, CollectionKind $kind): void
    {
        $balance = (float) $invoice->balance;

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => 'El monto no puede superar el saldo pendiente de la factura.',
            ]);
        }

        if ($kind === CollectionKind::Full && abs($amount - $balance) > 0.000001) {
            throw ValidationException::withMessages([
                'amount' => 'El monto debe ser igual al saldo pendiente para un cobro total.',
            ]);
        }

        if ($kind === CollectionKind::Partial && $amount >= $balance) {
            throw ValidationException::withMessages([
                'amount' => 'El monto debe ser menor al saldo pendiente para un cobro parcial.',
            ]);
        }

        $pendingAmount = (float) CollectionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', CollectionStatus::Pending)
            ->sum('amount');

        if ($amount > $balance - $pendingAmount + 0.000001) {
            throw ValidationException::withMessages([
                'amount' => 'El monto supera el saldo disponible tras los cobros pendientes de esta factura.',
            ]);
        }
    }
}
