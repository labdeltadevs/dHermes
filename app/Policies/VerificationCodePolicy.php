<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VerificationCode;
use App\Policies\Concerns\AuthorizesTenant;
use Illuminate\Database\Eloquent\Model;

class VerificationCodePolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $user->currentMembership()?->role->canApprove() ?? false;
    }

    public function view(User $user, VerificationCode $verificationCode): bool
    {
        $membership = $this->membership($user, $verificationCode);

        if ($membership === null) {
            return false;
        }

        if ($membership->role->canApprove()) {
            return true;
        }

        $verifiable = $verificationCode->verifiable;

        return $verifiable instanceof Model && $user->can('view', $verifiable);
    }

    public function issue(User $user, Model $verifiable): bool
    {
        return $user->can('view', $verifiable);
    }

    public function revoke(User $user, VerificationCode $verificationCode): bool
    {
        return $this->isApprover($user, $verificationCode);
    }
}
