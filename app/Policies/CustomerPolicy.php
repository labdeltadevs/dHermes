<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class CustomerPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::PortfolioView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->canAccessCustomer($customer);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Customer $customer): bool
    {
        return false;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
