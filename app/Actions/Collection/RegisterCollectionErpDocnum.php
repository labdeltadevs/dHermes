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

/**
 * Registra el DocNum del ERP en una cobranza que ya nació aceptada
 * automáticamente y que aún no fue conciliada contra SAP.
 */
final class RegisterCollectionErpDocnum
{
    public function handle(User $actor, CollectionEvent $collectionEvent, string $erpDocnum): CollectionEvent
    {
        abort_unless($collectionEvent->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('accept', $collectionEvent);

        if ($collectionEvent->status !== CollectionStatus::Accepted) {
            throw ValidationException::withMessages([
                'status' => 'Solo se puede registrar el DocNum de cobranzas aceptadas.',
            ]);
        }

        if ($collectionEvent->erp_docnum !== null) {
            throw ValidationException::withMessages([
                'erp_docnum' => 'Esta cobranza ya tiene un DocNum registrado.',
            ]);
        }

        $validated = Validator::make(
            ['erp_docnum' => $erpDocnum],
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
            ],
            [
                'erp_docnum.required' => 'El DocNum del ERP es obligatorio.',
                'erp_docnum.regex' => 'El DocNum del ERP debe ser numérico.',
                'erp_docnum.unique' => 'El DocNum del ERP ya fue registrado en esta empresa.',
            ],
        )->validate();

        return DB::transaction(function () use ($actor, $collectionEvent, $validated): CollectionEvent {
            $collectionEvent->update([
                'erp_docnum' => $validated['erp_docnum'],
            ]);

            app(AuditLogger::class)->record(
                $collectionEvent->tenant_id,
                'collection.docnum_registered',
                CollectionEvent::class,
                $collectionEvent->id,
                [
                    'erp_docnum' => $validated['erp_docnum'],
                    'number_code' => $collectionEvent->number_code,
                    'amount' => $collectionEvent->amount,
                    'currency' => $collectionEvent->currency,
                ],
                $actor,
            );

            return $collectionEvent->refresh();
        });
    }
}
