<?php

namespace App\Support\Verification;

enum VerificationResult: string
{
    case Valid = 'valid';
    case Tampered = 'tampered';
    case Revoked = 'revoked';
    case NotFound = 'not_found';

    public function isValid(): bool
    {
        return $this === self::Valid;
    }
}
