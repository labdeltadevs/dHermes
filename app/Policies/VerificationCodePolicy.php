<?php

namespace App\Policies;

use App\Enums\CollectionStatus;
use App\Enums\Permission;
use App\Models\CollectionEvent;
use App\Models\User;
use App\Models\VerificationCode;
use App\Policies\Concerns\AuthorizesTenant;
use Illuminate\Database\Eloquent\Model;

class VerificationCodePolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::VerificationCodesView);
    }

    public function view(User $user, VerificationCode $verificationCode): bool
    {
        if (! $this->isMember($user, $verificationCode)) {
            return false;
        }

        if ($this->hasPermission($user, $verificationCode, Permission::VerificationCodesView)) {
            return true;
        }

        $verifiable = $verificationCode->verifiable;

        return $verifiable instanceof Model && $user->can('view', $verifiable);
    }

    public function issue(User $user, Model $verifiable): bool
    {
        if ($verifiable instanceof CollectionEvent && $verifiable->status !== CollectionStatus::Accepted) {
            return false;
        }

        return $user->can('view', $verifiable);
    }

    public function revoke(User $user, VerificationCode $verificationCode): bool
    {
        return $this->hasPermission($user, $verificationCode, Permission::VerificationCodesView);
    }
}
