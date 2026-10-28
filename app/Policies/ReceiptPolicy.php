<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Receipt;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class ReceiptPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::ReceiptsView);
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $user->canAccessReceipt($receipt);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return false;
    }

    public function delete(User $user, Receipt $receipt): bool
    {
        return false;
    }
}
