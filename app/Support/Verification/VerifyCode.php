<?php

namespace App\Support\Verification;

use App\Contracts\Verifiable;
use App\Models\VerificationCode;
use App\Support\Tenancy\CurrentTenant;

final class VerifyCode
{
    public function __construct(private readonly VerificationSigner $signer) {}

    public function handle(string $code): VerificationResult
    {
        $verificationCode = $this->find($code);

        if ($verificationCode === null) {
            return VerificationResult::NotFound;
        }

        if ($verificationCode->isRevoked()) {
            return VerificationResult::Revoked;
        }

        app(CurrentTenant::class)->set($verificationCode->tenant_id);

        $subject = $verificationCode->verifiable;

        if (! $subject instanceof Verifiable) {
            return VerificationResult::Tampered;
        }

        return $this->signer->matches($subject, $verificationCode->signature)
            ? VerificationResult::Valid
            : VerificationResult::Tampered;
    }

    public function find(string $code): ?VerificationCode
    {
        return VerificationCode::withoutGlobalScope('tenant')
            ->where('code', $code)
            ->first();
    }
}
