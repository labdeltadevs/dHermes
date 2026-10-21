<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveCashSession
{
    public function handle(User $supervisor, CashSession $cashSession): CashSession
    {
        abort_unless($cashSession->tenant_id === $supervisor->currentTenantId(), 403);

        Gate::authorize('approve', $cashSession);

        if ($cashSession->status !== CashSessionStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden aprobar arqueos enviados.',
            ]);
        }

        return DB::transaction(function () use ($supervisor, $cashSession): CashSession {
            $cashSession->update([
                'status' => CashSessionStatus::Approved,
                'approved_by' => $supervisor->id,
                'approved_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                $cashSession->tenant_id,
                'cash_session.approved',
                CashSession::class,
                $cashSession->id,
                [
                    'expected_amount' => $cashSession->expected_amount,
                    'counted_amount' => $cashSession->counted_amount,
                    'difference' => $cashSession->difference,
                ],
                $supervisor,
            );

            return $cashSession->refresh();
        });
    }

    public function reject(User $supervisor, CashSession $cashSession, string $reason): CashSession
    {
        abort_unless($cashSession->tenant_id === $supervisor->currentTenantId(), 403);

        Gate::authorize('reject', $cashSession);

        if ($cashSession->status !== CashSessionStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden rechazar arqueos enviados.',
            ]);
        }

        return DB::transaction(function () use ($supervisor, $cashSession, $reason): CashSession {
            $cashSession->update([
                'status' => CashSessionStatus::Rejected,
                'notes' => $reason,
            ]);

            app(AuditLogger::class)->record(
                $cashSession->tenant_id,
                'cash_session.rejected',
                CashSession::class,
                $cashSession->id,
                ['reason' => $reason],
                $supervisor,
            );

            return $cashSession->refresh();
        });
    }
}
