<?php

namespace App\Support\Verification;

use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Gate;

final class RevokeVerificationCode
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $actor, VerificationCode $code): VerificationCode
    {
        abort_unless($code->tenant_id === $actor->currentTenantId(), 403);

        Gate::authorize('revoke', $code);

        if ($code->isRevoked()) {
            return $code;
        }

        $code->forceFill(['revoked_at' => now()])->save();

        $this->audit->record(
            $code->tenant_id,
            'verification.revoked',
            VerificationCode::class,
            $code->id,
            [
                'verifiable_type' => $code->verifiable_type,
                'verifiable_id' => $code->verifiable_id,
                'code' => $code->code,
            ],
            $actor,
        );

        return $code->refresh();
    }
}
