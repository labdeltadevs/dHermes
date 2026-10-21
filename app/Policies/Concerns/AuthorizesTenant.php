<?php

namespace App\Policies\Concerns;

use App\Enums\TenantRole;
use App\Models\TenantUser;
use App\Models\User;
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

    protected function role(User $user, Model|string $subject): ?TenantRole
    {
        return $this->membership($user, $subject)?->role;
    }

    protected function isApprover(User $user, Model|string $subject): bool
    {
        return $this->role($user, $subject)?->canApprove() ?? false;
    }
}
