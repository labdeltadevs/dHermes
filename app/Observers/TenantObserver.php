<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Support\Permissions\TenantRoleSyncer;

final class TenantObserver
{
    public function __construct(private readonly TenantRoleSyncer $syncer) {}

    public function created(Tenant $tenant): void
    {
        $this->syncer->provisionTenant($tenant->id);
    }
}
