<?php

namespace App\Actions\Collection;

use App\Enums\CollectionStatus;
use App\Models\CollectionEvent;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MatchCollection
{
    public function handle(User $actor, CollectionEvent $collectionEvent, Receipt $receipt): CollectionEvent
    {
        abort_unless($collectionEvent->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('match', $collectionEvent);

        if ($collectionEvent->tenant_id !== $receipt->tenant_id) {
            throw ValidationException::withMessages([
                'receipt_id' => 'El comprobante pertenece a otra empresa.',
            ]);
        }

        if ($receipt->cancelled) {
            throw ValidationException::withMessages([
                'receipt_id' => 'El comprobante está anulado.',
            ]);
        }

        if ($collectionEvent->status !== CollectionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden conciliar cobranzas pendientes.',
            ]);
        }

        $invoice = $collectionEvent->invoice;

        if ($invoice === null) {
            throw ValidationException::withMessages([
                'receipt_id' => 'La factura del cobro no está disponible.',
            ]);
        }

        if ($receipt->customer_id !== $invoice->customer_id) {
            throw ValidationException::withMessages([
                'receipt_id' => 'El comprobante pertenece a otro cliente.',
            ]);
        }

        if ($receipt->currency !== $collectionEvent->currency) {
            throw ValidationException::withMessages([
                'receipt_id' => 'La moneda del comprobante no coincide con la del cobro.',
            ]);
        }

        $isApplied = $receipt->applications()
            ->where('tenant_id', $collectionEvent->tenant_id)
            ->where('invoice_id', $invoice->id)
            ->exists();

        if (! $isApplied) {
            throw ValidationException::withMessages([
                'receipt_id' => 'El comprobante no está aplicado a la factura del cobro.',
            ]);
        }

        return DB::transaction(function () use ($actor, $collectionEvent, $receipt): CollectionEvent {
            $collectionEvent->update([
                'status' => CollectionStatus::Matched,
                'matched_receipt_id' => $receipt->id,
                'matched_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                $collectionEvent->tenant_id,
                'collection.matched',
                CollectionEvent::class,
                $collectionEvent->id,
                [
                    'receipt_id' => $receipt->id,
                    'amount' => $collectionEvent->amount,
                    'currency' => $collectionEvent->currency,
                ],
                $actor,
            );

            return $collectionEvent->refresh();
        });
    }

    public function markDiscrepancy(User $actor, CollectionEvent $collectionEvent, string $reason): CollectionEvent
    {
        abort_unless($collectionEvent->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('match', $collectionEvent);

        if ($collectionEvent->status !== CollectionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden marcar cobranzas pendientes.',
            ]);
        }

        return DB::transaction(function () use ($actor, $collectionEvent, $reason): CollectionEvent {
            $collectionEvent->update(['status' => CollectionStatus::Discrepancy]);

            app(AuditLogger::class)->record(
                $collectionEvent->tenant_id,
                'collection.discrepancy',
                CollectionEvent::class,
                $collectionEvent->id,
                ['reason' => $reason],
                $actor,
            );

            return $collectionEvent->refresh();
        });
    }
}
