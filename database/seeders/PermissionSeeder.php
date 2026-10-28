<?php

namespace Database\Seeders;

use App\Support\Permissions\TenantRoleSyncer;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantRoleSyncer::class)->syncAll();
    }
}
