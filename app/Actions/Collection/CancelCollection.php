<?php

namespace App\Actions\Collection;

use App\Enums\CollectionStatus;
use App\Models\CollectionEvent;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CancelCollection
{
    public function handle(User $actor, CollectionEvent $collectionEvent, string $reason): CollectionEvent
    {
        abort_unless($collectionEvent->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('cancel', $collectionEvent);

        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'El motivo de la anulación es obligatorio.',
        ])->validate();

        $cancellable = $collectionEvent->status === CollectionStatus::Pending
            || ($collectionEvent->status === CollectionStatus::Accepted && $collectionEvent->erp_docnum === null);

        if (! $cancellable) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden anular cobranzas pendientes o aceptadas sin DocNum.',
            ]);
        }

        return DB::transaction(function () use ($actor, $collectionEvent, $validated): CollectionEvent {
            $collectionEvent->update(['status' => CollectionStatus::Cancelled]);

            app(AuditLogger::class)->record(
                $collectionEvent->tenant_id,
                'collection.cancelled',
                CollectionEvent::class,
                $collectionEvent->id,
                [
                    'reason' => $validated['reason'],
                    'amount' => $collectionEvent->amount,
                    'currency' => $collectionEvent->currency,
                ],
                $actor,
            );

            return $collectionEvent->refresh();
        });
    }
}
