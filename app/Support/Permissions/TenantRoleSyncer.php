<?php

namespace App\Support\Permissions;

use App\Enums\Permission as PermissionEnum;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Closure;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class TenantRoleSyncer
{
    private const GUARD = 'web';

    private bool $catalogReady = false;

    /**
     * @var array<string, bool>
     */
    private array $preparedTenants = [];

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function syncAll(): void
    {
        $this->ensureCatalog();

        foreach (Tenant::query()->get() as $tenant) {
            $this->provisionTenant($tenant->id);
        }

        $memberships = TenantUser::query()
            ->withoutGlobalScope('tenant')
            ->get();

        foreach ($memberships as $membership) {
            $this->syncMembership($membership);
        }

        $this->registrar->forgetCachedPermissions();
    }

    public function syncMembership(TenantUser $membership): void
    {
        $user = User::query()->find($membership->user_id);

        if ($user === null) {
            return;
        }

        $this->provisionTenant($membership->tenant_id);

        try {
            $this->withTeam($membership->tenant_id, function () use ($user, $membership): void {
                $this->forgetRelations($user);

                if (! $membership->active) {
                    $user->syncRoles([]);

                    return;
                }

                $user->syncRoles([$membership->role->value]);
            });
        } finally {
            $this->forgetRelations($user);
        }
    }

    public function removeMembership(TenantUser $membership): void
    {
        $user = User::query()->find($membership->user_id);

        if ($user === null) {
            return;
        }

        try {
            $this->withTeam($membership->tenant_id, function () use ($user): void {
                $this->forgetRelations($user);
                $user->syncRoles([]);
            });
        } finally {
            $this->forgetRelations($user);
        }
    }

    public function provisionTenant(string $tenantId): void
    {
        if (isset($this->preparedTenants[$tenantId])) {
            return;
        }

        $this->ensureCatalog();

        $this->withTeam($tenantId, function () use ($tenantId): void {
            foreach (TenantRole::cases() as $role) {
                $spatieRole = Role::query()->firstOrCreate([
                    'name' => $role->value,
                    'guard_name' => self::GUARD,
                    'tenant_id' => $tenantId,
                ]);

                $spatieRole->syncPermissions($this->permissionNames($role));
            }
        });

        $this->preparedTenants[$tenantId] = true;
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withTeam(string $tenantId, Closure $callback): mixed
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
        }
    }

    private function ensureCatalog(): void
    {
        if ($this->catalogReady) {
            return;
        }

        foreach (PermissionEnum::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, self::GUARD);
        }

        $this->catalogReady = true;
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(TenantRole $role): array
    {
        return array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            $role->permissions(),
        );
    }

    private function forgetRelations(User $user): void
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
