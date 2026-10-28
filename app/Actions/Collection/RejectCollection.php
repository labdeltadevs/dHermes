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

final class RejectCollection
{
    public function handle(User $actor, CollectionEvent $collectionEvent, string $reason): CollectionEvent
    {
        abort_unless($collectionEvent->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('reject', $collectionEvent);

        $validated = Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'El motivo del rechazo es obligatorio.',
        ])->validate();

        if ($collectionEvent->status !== CollectionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden rechazar cobranzas pendientes.',
            ]);
        }

        return DB::transaction(function () use ($actor, $collectionEvent, $validated): CollectionEvent {
            $collectionEvent->update([
                'status' => CollectionStatus::Rejected,
                'rejection_reason' => $validated['reason'],
                'erp_docnum' => null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                $collectionEvent->tenant_id,
                'collection.rejected',
                CollectionEvent::class,
                $collectionEvent->id,
                ['reason' => $validated['reason']],
                $actor,
            );

            return $collectionEvent->refresh();
        });
    }
}
