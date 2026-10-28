<?php

namespace Database\Seeders;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bootstraps the tenant and the master administrator required to sign in.
 *
 * It is safe to run repeatedly: existing records are reused and the stored
 * password is never overwritten.
 */
class MasterAdminSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = '$Laboratorios.Delta.Dhermes.0101$';

    public function run(): void
    {
        $tenantCode = (string) config('dhermes.tenant.code', 'DHERMES');
        $tenantName = (string) config('dhermes.tenant.name', 'dHermes');
        $tenantTimezone = (string) config('dhermes.tenant.timezone', 'America/La_Paz');
        $tenantCurrency = (string) config('dhermes.tenant.local_currency', 'BOB');
        $adminName = (string) config('dhermes.admin.name', 'Administrador Maestro');
        $adminEmail = (string) config('dhermes.admin.email', 'kevinaltamirano@laboratoriosdelta.net');
        $adminPassword = (string) config('dhermes.admin.password', self::DEFAULT_PASSWORD);

        $tenant = DB::transaction(function () use (
            $tenantCode,
            $tenantName,
            $tenantTimezone,
            $tenantCurrency,
            $adminName,
            $adminEmail,
            $adminPassword,
        ): Tenant {
            $tenant = Tenant::query()->firstOrCreate(
                ['code' => $tenantCode],
                [
                    'name' => $tenantName,
                    'timezone' => $tenantTimezone,
                    'local_currency' => $tenantCurrency,
                    'active' => true,
                ],
            );

            $user = User::query()->firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password_hash' => bcrypt($adminPassword),
                    'active' => true,
                    'is_platform_admin' => true,
                ],
            );

            if (! $user->is_platform_admin) {
                $user->forceFill(['is_platform_admin' => true])->save();
            }

            app(CurrentTenant::class)->set($tenant->id);

            TenantUser::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'role' => TenantRole::Admin,
                    'sales_rep_id' => null,
                    'active' => true,
                ],
            );

            return $tenant;
        });

        $this->command?->info(sprintf(
            'Admin maestro disponible: %s (tenant %s).',
            $adminEmail,
            $tenant->code,
        ));

        if ($adminPassword === self::DEFAULT_PASSWORD && app()->isProduction()) {
            $this->command?->warn(
                'El admin maestro usa la clave por defecto. Define DHERMES_ADMIN_PASSWORD antes de usarlo en producción.'
            );
        }
    }
}
