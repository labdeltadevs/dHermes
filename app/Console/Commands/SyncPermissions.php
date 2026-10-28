<?php

namespace App\Console\Commands;

use App\Support\Permissions\TenantRoleSyncer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dhermes:permissions:sync')]
#[Description('Sincroniza los permisos, los roles por empresa y las asignaciones de usuarios')]
class SyncPermissions extends Command
{
    public function handle(TenantRoleSyncer $syncer): int
    {
        $syncer->syncAll();

        $this->components->info('Permisos, roles por empresa y asignaciones sincronizados.');

        return self::SUCCESS;
    }
}
