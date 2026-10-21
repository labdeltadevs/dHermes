<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReopenCashSession
{
    public function handle(User $seller, CashSession $cashSession): CashSession
    {
        abort_unless($cashSession->tenant_id === $seller->currentTenantId(), 403);

        Gate::authorize('reopen', $cashSession);

        if ($cashSession->status !== CashSessionStatus::Rejected) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden reabrir arqueos rechazados.',
            ]);
        }

        return DB::transaction(function () use ($seller, $cashSession): CashSession {
            $cashSession->update([
                'status' => CashSessionStatus::Open,
                'closed_at' => null,
                'counted_amount' => null,
                'difference' => null,
            ]);

            app(AuditLogger::class)->record(
                $cashSession->tenant_id,
                'cash_session.reopened',
                CashSession::class,
                $cashSession->id,
                ['notes' => $cashSession->notes],
                $seller,
            );

            return $cashSession->refresh();
        });
    }
}
