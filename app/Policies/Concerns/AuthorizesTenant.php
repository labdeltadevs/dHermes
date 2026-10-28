<?php

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Permissions\TenantRoleSyncer;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesTenant
{
    protected function membership(User $user, Model|string $subject): ?TenantUser
    {
        $tenantId = $subject instanceof Model ? $subject->getAttribute('tenant_id') : $subject;

        return $tenantId === null ? null : $user->membershipFor($tenantId);
    }

    protected function isMember(User $user, Model|string $subject): bool
    {
        return $this->membership($user, $subject) !== null;
    }

    protected function hasCurrentPermission(User $user, Permission $permission): bool
    {
        return $user->currentMembership() !== null
            && $user->can($permission->value);
    }

    protected function hasPermission(User $user, Model|string $subject, Permission $permission): bool
    {
        $tenantId = $subject instanceof Model ? $subject->getAttribute('tenant_id') : $subject;

        if (! is_string($tenantId) || $tenantId === '' || ! $this->isMember($user, $tenantId)) {
            return false;
        }

        try {
            return app(TenantRoleSyncer::class)->withTeam(
                $tenantId,
                function () use ($user, $permission): bool {
                    $user->unsetRelation('roles')->unsetRelation('permissions');

                    return $user->can($permission->value);
                },
            );
        } finally {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
