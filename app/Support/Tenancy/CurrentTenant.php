<?php

namespace App\Support\Tenancy;

use App\Models\User;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

final class CurrentTenant
{
    private ?string $tenantId = null;

    public function __construct(private readonly PermissionRegistrar $permissions) {}

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function set(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->permissions->setPermissionsTeamId($tenantId);
        $this->forgetPermissionRelations();
    }

    public function clear(): void
    {
        $this->set(null);
    }

    public function requireId(): string
    {
        if ($this->tenantId === null) {
            throw new RuntimeException('No active tenant context.');
        }

        return $this->tenantId;
    }

    private function forgetPermissionRelations(): void
    {
        $user = auth()->user();

        if ($user instanceof User) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
