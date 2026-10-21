<?php

namespace App\Actions\Collection;

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        $validated = Validator::make($data, [
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'local_amount' => ['nullable', 'numeric'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'collected_on' => ['required', 'date'],
            'kind' => ['required', Rule::enum(CollectionKind::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

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
        $balance = (float) $invoice->balance;
        $kind = $validated['kind'] instanceof CollectionKind
            ? $validated['kind']
            : CollectionKind::from((string) $validated['kind']);

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

        return DB::transaction(function () use ($seller, $invoice, $validated): CollectionEvent {
            $collectionEvent = CollectionEvent::create([
                ...$validated,
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'seller_user_id' => $seller->id,
                'status' => CollectionStatus::Pending,
            ]);

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
                ],
                $seller,
            );

            return $collectionEvent;
        });
    }
}
