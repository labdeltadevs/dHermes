<?php

namespace App\Contracts;

interface Verifiable
{
    /**
     * @return array<string, scalar|null>
     */
    public function verificationPayload(): array;

    public function verificationLabel(): string;
}
