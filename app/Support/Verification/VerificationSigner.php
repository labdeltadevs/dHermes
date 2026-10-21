<?php

namespace App\Support\Verification;

use App\Contracts\Verifiable;
use Illuminate\Database\Eloquent\Model;

final class VerificationSigner
{
    public function sign(Verifiable&Model $verifiable): string
    {
        return hash_hmac('sha256', $this->canonical($verifiable), (string) config('verification.key'));
    }

    public function matches(Verifiable&Model $verifiable, string $signature): bool
    {
        return hash_equals($signature, $this->sign($verifiable));
    }

    private function canonical(Verifiable&Model $verifiable): string
    {
        return json_encode(
            $verifiable->verificationPayload(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
