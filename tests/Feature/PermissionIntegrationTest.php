<?php

use App\Enums\Permission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Permissions\TenantRoleSyncer;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

function allowsInTenant(User $user, Tenant $tenant, Permission $permission): bool
{
    return app(TenantRoleSyncer::class)->withTeam($tenant->id, function () use ($user, $permission): bool {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user->can($permission->value);
    });
}

function membershipOf(User $user, Tenant $tenant): TenantUser
{
    return TenantUser::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
}

it('creates the global permission catalog and one role set per tenant', function () {
    $tenant = tenantContext();

    expect(PermissionModel::query()->count())->toBe(count(Permission::cases()))
        ->and(Role::query()->where('tenant_id', $tenant->id)->count())->toBe(count(TenantRole::cases()));
});

it('assigns the spatie role that mirrors the membership', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $seller = memberFor($tenant, TenantRole::Seller);

    expect($admin->hasRole(TenantRole::Admin->value))->toBeTrue()
        ->and($seller->hasRole(TenantRole::Seller->value))->toBeTrue()
        ->and($seller->hasRole(TenantRole::Admin->value))->toBeFalse();
});

it('grants permissions according to the role', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    $seller = memberFor($tenant, TenantRole::Seller);
    $collector = memberFor($tenant, TenantRole::Collector);

    expect(allowsInTenant($admin, $tenant, Permission::UsersManage))->toBeTrue()
        ->and(allowsInTenant($admin, $tenant, Permission::AuditView))->toBeTrue()
        ->and(allowsInTenant($supervisor, $tenant, Permission::UsersManage))->toBeFalse()
        ->and(allowsInTenant($supervisor, $tenant, Permission::CollectionsApprove))->toBeTrue()
        ->and(allowsInTenant($seller, $tenant, Permission::CollectionsCreate))->toBeTrue()
        ->and(allowsInTenant($seller, $tenant, Permission::CollectionsApprove))->toBeFalse()
        ->and(allowsInTenant($collector, $tenant, Permission::CashSessionsOperate))->toBeTrue()
        ->and(allowsInTenant($collector, $tenant, Permission::CashSessionsApprove))->toBeFalse();
});

it('isolates roles and permissions between tenants', function () {
    $tenantA = tenantContext();
    $adminA = memberFor($tenantA, TenantRole::Admin);
    $tenantB = Tenant::factory()->create();
    $sellerB = memberFor($tenantB, TenantRole::Seller);

    expect(allowsInTenant($adminA, $tenantA, Permission::UsersManage))->toBeTrue()
        ->and(allowsInTenant($adminA, $tenantB, Permission::UsersManage))->toBeFalse()
        ->and(allowsInTenant($sellerB, $tenantB, Permission::UsersManage))->toBeFalse()
        ->and(allowsInTenant($sellerB, $tenantA, Permission::UsersManage))->toBeFalse();
});

it('syncs the role when the membership role changes', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Seller);

    membershipOf($user, $tenant)->update(['role' => TenantRole::Supervisor]);

    expect(allowsInTenant($user, $tenant, Permission::CollectionsApprove))->toBeTrue()
        ->and(allowsInTenant($user, $tenant, Permission::CollectionsCreate))->toBeFalse();
});

it('revokes the role when the membership is deactivated', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    membershipOf($user, $tenant)->update(['active' => false]);

    expect(allowsInTenant($user, $tenant, Permission::UsersManage))->toBeFalse();
});

it('revokes the role when the membership is deleted', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    membershipOf($user, $tenant)->delete();

    expect(allowsInTenant($user, $tenant, Permission::UsersManage))->toBeFalse();
});

it('backfills missing assignments and is idempotent', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    DB::table('model_has_roles')->delete();

    $this->artisan('dhermes:permissions:sync')->assertSuccessful();

    expect(allowsInTenant($admin, $tenant, Permission::UsersManage))->toBeTrue();

    $roles = Role::query()->where('tenant_id', $tenant->id)->count();
    $assignments = DB::table('model_has_roles')->count();

    $this->artisan('dhermes:permissions:sync')->assertSuccessful();

    expect(Role::query()->where('tenant_id', $tenant->id)->count())->toBe($roles)
        ->and(DB::table('model_has_roles')->count())->toBe($assignments);
});

it('keeps the seeded master administrator authorized', function () {
    $this->seed();

    $user = User::query()->where('email', 'admin@dhermes.local')->firstOrFail();
    $tenant = Tenant::query()->where('code', 'DHERMES')->firstOrFail();

    app(CurrentTenant::class)->set($tenant->id);

    expect(allowsInTenant($user, $tenant, Permission::UsersManage))->toBeTrue();
});
