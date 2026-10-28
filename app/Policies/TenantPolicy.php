<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function toggleActive(User $user, Tenant $tenant): bool
    {
        return $this->isPlatformAdmin($user);
    }

    private function isPlatformAdmin(User $user): bool
    {
        return $user->active && $user->is_platform_admin;
    }
}
