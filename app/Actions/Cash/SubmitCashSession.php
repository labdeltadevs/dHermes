<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SubmitCashSession
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $seller, CashSession $cashSession, array $data): CashSession
    {
        abort_unless($cashSession->tenant_id === $seller->currentTenantId(), 403);

        Gate::authorize('submit', $cashSession);

        if ($cashSession->status !== CashSessionStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden enviar arqueos abiertos.',
            ]);
        }

        $validated = Validator::make($data, [
            'counted_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($seller, $cashSession, $validated): CashSession {
            $expected = (float) $cashSession->items()->sum('amount');
            $counted = (float) $validated['counted_amount'];

            $cashSession->update([
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => $counted - $expected,
                'status' => CashSessionStatus::Submitted,
                'closed_at' => now(),
                'notes' => $validated['notes'] ?? $cashSession->notes,
            ]);

            app(AuditLogger::class)->record(
                $cashSession->tenant_id,
                'cash_session.submitted',
                CashSession::class,
                $cashSession->id,
                [
                    'expected_amount' => $expected,
                    'counted_amount' => $counted,
                    'difference' => $counted - $expected,
                ],
                $seller,
            );

            return $cashSession->refresh();
        });
    }
}
