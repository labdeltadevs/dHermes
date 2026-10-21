<?php

namespace App\Support\Verification;

use App\Contracts\Verifiable;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class IssueVerificationCode
{
    public function __construct(
        private readonly VerificationSigner $signer,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Verifiable&Model $verifiable): VerificationCode
    {
        abort_unless($verifiable->getAttribute('tenant_id') === $actor->currentTenantId(), 403);

        Gate::authorize('issue', [VerificationCode::class, $verifiable]);

        $tenantId = (string) $verifiable->getAttribute('tenant_id');

        $existing = VerificationCode::query()
            ->where('tenant_id', $tenantId)
            ->where('verifiable_type', $verifiable::class)
            ->where('verifiable_id', $verifiable->getKey())
            ->whereNull('revoked_at')
            ->latest('issued_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $code = VerificationCode::create([
            'tenant_id' => $tenantId,
            'verifiable_type' => $verifiable::class,
            'verifiable_id' => $verifiable->getKey(),
            'code' => Str::random((int) config('verification.code_length', 32)),
            'signature' => $this->signer->sign($verifiable),
            'issued_at' => now(),
        ]);

        $this->audit->record(
            $tenantId,
            'verification.issued',
            VerificationCode::class,
            $code->id,
            [
                'verifiable_type' => $verifiable::class,
                'verifiable_id' => $verifiable->getKey(),
                'code' => $code->code,
            ],
            $actor,
        );

        return $code;
    }
}
