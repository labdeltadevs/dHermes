<?php

namespace App\Policies;

use App\Enums\CollectionStatus;
use App\Enums\Permission;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenant;

class CollectionEventPolicy
{
    use AuthorizesTenant;

    public function viewAny(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::CollectionsView);
    }

    public function view(User $user, CollectionEvent $collectionEvent): bool
    {
        if (! $this->isMember($user, $collectionEvent)) {
            return false;
        }

        if ($this->hasPermission($user, $collectionEvent, Permission::CollectionsApprove)) {
            return true;
        }

        if ($collectionEvent->seller_user_id === $user->id) {
            return true;
        }

        return $collectionEvent->invoice !== null && $user->canAccessInvoice($collectionEvent->invoice);
    }

    public function create(User $user, Invoice $invoice): bool
    {
        if (! $invoice->isOpen()) {
            return false;
        }

        return $this->hasPermission($user, $invoice, Permission::CollectionsCreate)
            && $user->canAccessInvoice($invoice);
    }

    public function createFlash(User $user): bool
    {
        return $this->hasCurrentPermission($user, Permission::CollectionsCreateFlash);
    }

    public function update(User $user, CollectionEvent $collectionEvent): bool
    {
        return $collectionEvent->status === CollectionStatus::Pending
            && $collectionEvent->seller_user_id === $user->id
            && $this->hasPermission($user, $collectionEvent, Permission::CollectionsCreate);
    }

    public function delete(User $user, CollectionEvent $collectionEvent): bool
    {
        return false;
    }

    public function accept(User $user, CollectionEvent $collectionEvent): bool
    {
        return $this->hasPermission($user, $collectionEvent, Permission::CollectionsApprove);
    }

    public function reject(User $user, CollectionEvent $collectionEvent): bool
    {
        return $this->hasPermission($user, $collectionEvent, Permission::CollectionsApprove);
    }

    public function cancel(User $user, CollectionEvent $collectionEvent): bool
    {
        if ($collectionEvent->status !== CollectionStatus::Pending) {
            return false;
        }

        if ($this->hasPermission($user, $collectionEvent, Permission::CollectionsCancelAny)) {
            return true;
        }

        return $collectionEvent->seller_user_id === $user->id
            && $collectionEvent->collected_on->isToday()
            && $this->hasPermission($user, $collectionEvent, Permission::CollectionsCreate);
    }

    public function void(User $user, CollectionEvent $collectionEvent): bool
    {
        return $this->hasPermission($user, $collectionEvent, Permission::CollectionsVoid);
    }
}
