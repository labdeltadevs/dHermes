<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class AuditEventPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::AuditView);
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        return $this->hasPermission($user, $auditEvent, Permission::AuditView);
    }

    public function create(User $user): bool
    {
        return false;
    }
}
