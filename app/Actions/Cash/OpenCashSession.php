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

final class OpenCashSession
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $seller, array $data): CashSession
    {
        Gate::authorize('create', CashSession::class);

        $data['currency'] = strtoupper(trim((string) ($data['currency'] ?? '')));

        $validated = Validator::make($data, [
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($seller, $validated): CashSession {
            $existing = CashSession::query()
                ->where('seller_user_id', $seller->id)
                ->where('currency', $validated['currency'])
                ->where('status', CashSessionStatus::Open)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'currency' => 'Ya tienes un arqueo abierto en esta moneda.',
                ]);
            }

            $cashSession = CashSession::create([
                ...$validated,
                'tenant_id' => $seller->currentTenantId(),
                'seller_user_id' => $seller->id,
                'opened_at' => now(),
                'status' => CashSessionStatus::Open,
                'expected_amount' => 0,
            ]);

            app(AuditLogger::class)->record(
                $cashSession->tenant_id,
                'cash_session.opened',
                CashSession::class,
                $cashSession->id,
                ['currency' => $cashSession->currency],
                $seller,
            );

            return $cashSession;
        });
    }
}
