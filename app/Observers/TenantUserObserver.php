<?php

namespace App\Observers;

use App\Models\TenantUser;
use App\Support\Permissions\TenantRoleSyncer;

final class TenantUserObserver
{
    public function __construct(private readonly TenantRoleSyncer $syncer) {}

    public function created(TenantUser $tenantUser): void
    {
        $this->syncer->syncMembership($tenantUser);
    }

    public function updated(TenantUser $tenantUser): void
    {
        $this->syncer->syncMembership($tenantUser);
    }

    public function deleted(TenantUser $tenantUser): void
    {
        $this->syncer->removeMembership($tenantUser);
    }
}
