<?php

namespace App\Actions\Collection;

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Tenant;
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

/**
 * Registra un cobro "flash": un pago que se informa en el momento y cuya
 * factura todavía no existe en la cartera sincronizada desde SAP. El evento
 * queda flotante (sin invoice_id) hasta que un aprobador lo revise.
 */
final class RegisterFlashCollection
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $seller, array $data): CollectionEvent
    {
        $tenantId = $seller->currentTenantId();

        abort_if($tenantId === null, 403);

        Gate::authorize('createFlash', CollectionEvent::class);

        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $proof = $data['proof'] ?? null;

        $validated = Validator::make($data, [
            'amount' => ['required', 'numeric', 'gt:0'],
            'flash_invoice_number' => ['required', 'string', 'max:32'],
            'flash_cardcode' => ['required', 'string', 'max:32'],
            'flash_customer_name' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'collected_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'voucher_code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('collection_events', 'voucher_code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
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

        if (Carbon::parse($validated['collected_on'])->startOfDay()->isAfter(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'collected_on' => 'La fecha de cobro no puede ser posterior a hoy.',
            ]);
        }

        $storage = app(CollectionProofStorage::class);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($seller, $tenant, $tenantId, $validated, $paymentMethod, $voucherCode, $proof, $storage, &$storedPath): CollectionEvent {
                $collectionEvent = CollectionEvent::create([
                    'tenant_id' => $tenantId,
                    'invoice_id' => null,
                    'flash_invoice_number' => trim((string) $validated['flash_invoice_number']),
                    'flash_cardcode' => trim((string) $validated['flash_cardcode']),
                    'flash_customer_name' => trim((string) $validated['flash_customer_name']),
                    'seller_user_id' => $seller->id,
                    'amount' => $validated['amount'],
                    'currency' => $tenant->local_currency,
                    'local_amount' => null,
                    'payment_method' => $paymentMethod,
                    'collected_on' => $validated['collected_on'],
                    'kind' => CollectionKind::Partial,
                    'status' => CollectionStatus::Pending,
                    'notes' => $validated['notes'] ?? null,
                    'voucher_code' => $voucherCode,
                ]);

                if ($proof instanceof UploadedFile) {
                    $storedPath = $storage->store($collectionEvent, $proof);
                }

                app(AuditLogger::class)->record(
                    $tenantId,
                    'collection.created',
                    CollectionEvent::class,
                    $collectionEvent->id,
                    [
                        'flash' => true,
                        'invoice_id' => null,
                        'flash_invoice_number' => $collectionEvent->flash_invoice_number,
                        'flash_cardcode' => $collectionEvent->flash_cardcode,
                        'flash_customer_name' => $collectionEvent->flash_customer_name,
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
}
