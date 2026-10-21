<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class AuditEventPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $user->currentMembership()?->role->canApprove() ?? false;
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        return $this->isApprover($user, $auditEvent);
    }

    public function create(User $user): bool
    {
        return false;
    }
}
