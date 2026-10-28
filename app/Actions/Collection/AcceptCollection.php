<?php

namespace App\Actions\Collection;

use App\Enums\CollectionStatus;
use App\Models\CollectionEvent;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AcceptCollection
{
    public function handle(
        User $actor,
        CollectionEvent $collectionEvent,
        string $erpDocnum,
        bool $fundsReceived,
    ): CollectionEvent {
        abort_unless($collectionEvent->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('accept', $collectionEvent);

        $validated = Validator::make(
            ['erp_docnum' => $erpDocnum, 'funds_received' => $fundsReceived],
            [
                'erp_docnum' => [
                    'required',
                    'string',
                    'max:32',
                    'regex:/^\d+$/',
                    Rule::unique('collection_events', 'erp_docnum')
                        ->where('tenant_id', $collectionEvent->tenant_id)
                        ->ignore($collectionEvent->id),
                ],
                'funds_received' => ['accepted'],
            ],
            [
                'erp_docnum.required' => 'El DocNum del ERP es obligatorio.',
                'erp_docnum.regex' => 'El DocNum del ERP debe ser numérico.',
                'erp_docnum.unique' => 'El DocNum del ERP ya fue registrado en esta empresa.',
                'funds_received.accepted' => 'Debes confirmar la recepción del efectivo o comprobante.',
            ],
        )->validate();

        if ($collectionEvent->status !== CollectionStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden aceptar cobranzas pendientes.',
            ]);
        }

        return DB::transaction(function () use ($actor, $collectionEvent, $validated): CollectionEvent {
            $collectionEvent->update([
                'status' => CollectionStatus::Accepted,
                'erp_docnum' => $validated['erp_docnum'],
                'rejection_reason' => null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                $collectionEvent->tenant_id,
                'collection.accepted',
                CollectionEvent::class,
                $collectionEvent->id,
                [
                    'erp_docnum' => $validated['erp_docnum'],
                    'amount' => $collectionEvent->amount,
                    'currency' => $collectionEvent->currency,
                    'seller_user_id' => $collectionEvent->seller_user_id,
                    'funds_received' => true,
                ],
                $actor,
            );

            return $collectionEvent->refresh();
        });
    }
}
