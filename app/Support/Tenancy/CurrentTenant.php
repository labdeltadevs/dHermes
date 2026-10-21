<?php

namespace App\Support\Tenancy;

use RuntimeException;

final class CurrentTenant
{
    private ?string $tenantId = null;

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function set(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    public function requireId(): string
    {
        if ($this->tenantId === null) {
            throw new RuntimeException('No active tenant context.');
        }

        return $this->tenantId;
    }
}
