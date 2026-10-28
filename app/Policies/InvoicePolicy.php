<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class InvoicePolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::PortfolioView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canAccessInvoice($invoice);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
