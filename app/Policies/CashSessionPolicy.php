<?php

namespace App\Policies;

use App\Enums\CashSessionStatus;
use App\Enums\Permission;
use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class CashSessionPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::CashSessionsView);
    }

    public function view(User $user, CashSession $cashSession): bool
    {
        if (! $this->isMember($user, $cashSession)) {
            return false;
        }

        return $cashSession->seller_user_id === $user->id
            || $this->hasPermission($user, $cashSession, Permission::CashSessionsApprove);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::CashSessionsOperate);
    }

    public function update(User $user, CashSession $cashSession): bool
    {
        return $cashSession->status === CashSessionStatus::Open
            && $cashSession->seller_user_id === $user->id
            && $this->hasPermission($user, $cashSession, Permission::CashSessionsOperate);
    }

    public function submit(User $user, CashSession $cashSession): bool
    {
        return $this->update($user, $cashSession);
    }

    public function approve(User $user, CashSession $cashSession): bool
    {
        return $this->hasPermission($user, $cashSession, Permission::CashSessionsApprove);
    }

    public function reject(User $user, CashSession $cashSession): bool
    {
        return $this->hasPermission($user, $cashSession, Permission::CashSessionsApprove);
    }

    public function reopen(User $user, CashSession $cashSession): bool
    {
        return $cashSession->status === CashSessionStatus::Rejected
            && $cashSession->seller_user_id === $user->id
            && $this->hasPermission($user, $cashSession, Permission::CashSessionsOperate);
    }

    public function delete(User $user, CashSession $cashSession): bool
    {
        return false;
    }
}
