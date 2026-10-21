<?php

namespace App\Policies;

use App\Enums\CollectionStatus;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class CollectionEventPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $user->currentMembership() !== null;
    }

    public function view(User $user, CollectionEvent $collectionEvent): bool
    {
        $membership = $this->membership($user, $collectionEvent);

        if ($membership === null) {
            return false;
        }

        if ($membership->role->canApprove() || $collectionEvent->seller_user_id === $user->id) {
            return true;
        }

        return $collectionEvent->invoice !== null && $user->canAccessInvoice($collectionEvent->invoice);
    }

    public function create(User $user, Invoice $invoice): bool
    {
        $membership = $this->membership($user, $invoice);

        if ($membership === null || ! $membership->role->sells() || ! $invoice->isOpen()) {
            return false;
        }

        return $user->canAccessInvoice($invoice);
    }

    public function update(User $user, CollectionEvent $collectionEvent): bool
    {
        $membership = $this->membership($user, $collectionEvent);

        return $membership !== null
            && $membership->role->sells()
            && $collectionEvent->seller_user_id === $user->id
            && $collectionEvent->status === CollectionStatus::Pending;
    }

    public function delete(User $user, CollectionEvent $collectionEvent): bool
    {
        return false;
    }

    public function match(User $user, CollectionEvent $collectionEvent): bool
    {
        return $this->role($user, $collectionEvent)?->canMatch() ?? false;
    }

    public function void(User $user, CollectionEvent $collectionEvent): bool
    {
        return $this->role($user, $collectionEvent) === TenantRole::Admin;
    }
}
