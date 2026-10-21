<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\CashSessionItem;
use App\Models\CollectionEvent;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AddCashSessionItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, CashSession $cashSession, array $data): CashSessionItem
    {
        abort_unless($cashSession->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('update', $cashSession);

        if ($cashSession->status !== CashSessionStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'El arqueo ya no está abierto.',
            ]);
        }

        $validated = Validator::make($data, [
            'collection_event_id' => [
                'nullable',
                'uuid',
                Rule::exists('collection_events', 'id')->where('tenant_id', $cashSession->tenant_id),
            ],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
        ])->validate();

        try {
            return DB::transaction(function () use ($actor, $cashSession, $validated): CashSessionItem {
                if (($validated['collection_event_id'] ?? null) !== null) {
                    $event = CollectionEvent::query()
                        ->whereKey((string) $validated['collection_event_id'])
                        ->first();

                    if ($event === null) {
                        throw ValidationException::withMessages([
                            'collection_event_id' => 'El cobro seleccionado no existe.',
                        ]);
                    }

                    if ($event->seller_user_id !== $cashSession->seller_user_id) {
                        throw ValidationException::withMessages([
                            'collection_event_id' => 'El cobro no pertenece al vendedor del arqueo.',
                        ]);
                    }

                    if ($event->currency !== $cashSession->currency) {
                        throw ValidationException::withMessages([
                            'collection_event_id' => 'El cobro no coincide con la moneda del arqueo.',
                        ]);
                    }

                    if ($event->status !== CollectionStatus::Pending) {
                        throw ValidationException::withMessages([
                            'collection_event_id' => 'Solo se pueden incluir cobros pendientes.',
                        ]);
                    }

                    if (CashSessionItem::query()->where('collection_event_id', $event->id)->exists()) {
                        throw ValidationException::withMessages([
                            'collection_event_id' => 'Este cobro ya fue incluido en un arqueo.',
                        ]);
                    }
                }

                $item = CashSessionItem::create([
                    ...$validated,
                    'tenant_id' => $cashSession->tenant_id,
                    'cash_session_id' => $cashSession->id,
                ]);

                $cashSession->update([
                    'expected_amount' => $cashSession->items()->sum('amount'),
                ]);

                app(AuditLogger::class)->record(
                    $cashSession->tenant_id,
                    'cash_session.item_added',
                    CashSession::class,
                    $cashSession->id,
                    [
                        'item_id' => $item->id,
                        'amount' => $item->amount,
                        'payment_method' => $item->payment_method->value,
                    ],
                    $actor,
                );

                return $item;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'collection_event_id' => 'Este cobro ya fue incluido en un arqueo.',
            ]);
        }
    }
}
