<?php

namespace App\Policies;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class CashSessionPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $user->currentMembership() !== null;
    }

    public function view(User $user, CashSession $cashSession): bool
    {
        $membership = $this->membership($user, $cashSession);

        if ($membership === null) {
            return false;
        }

        return $membership->role->canApprove() || $cashSession->seller_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->currentMembership()?->role->sells() ?? false;
    }

    public function update(User $user, CashSession $cashSession): bool
    {
        $membership = $this->membership($user, $cashSession);

        return $membership !== null
            && $membership->role->sells()
            && $cashSession->seller_user_id === $user->id
            && $cashSession->status === CashSessionStatus::Open;
    }

    public function submit(User $user, CashSession $cashSession): bool
    {
        return $this->update($user, $cashSession);
    }

    public function approve(User $user, CashSession $cashSession): bool
    {
        return $this->isApprover($user, $cashSession);
    }

    public function reject(User $user, CashSession $cashSession): bool
    {
        return $this->isApprover($user, $cashSession);
    }

    public function reopen(User $user, CashSession $cashSession): bool
    {
        $membership = $this->membership($user, $cashSession);

        return $membership !== null
            && $membership->role->sells()
            && $cashSession->seller_user_id === $user->id
            && $cashSession->status === CashSessionStatus::Rejected;
    }

    public function delete(User $user, CashSession $cashSession): bool
    {
        return false;
    }
}
