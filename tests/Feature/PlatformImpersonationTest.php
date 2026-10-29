<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\Impersonation;
use Illuminate\Support\Facades\DB;

it('forbids tenant members without platform privileges from impersonating', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin)
        ->post(route('platform.tenants.manage', $tenant))
        ->assertForbidden();

    expect(session(Impersonation::KEY))->toBeNull();
});

it('lets a platform administrator impersonate an active tenant', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create(['name' => 'ACME SRL']);

    $tenantUsersBefore = DB::table('tenant_users')->count();
    $rolesBefore = DB::table('model_has_roles')->count();

    $this->actingAs($platformAdmin)
        ->post(route('platform.tenants.manage', $tenant))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(Impersonation::KEY, $tenant->id);

    expect(DB::table('tenant_users')->count())->toBe($tenantUsersBefore)
        ->and(DB::table('model_has_roles')->count())->toBe($rolesBefore);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'actor_user_id' => $platformAdmin->id,
        'action' => 'platform.impersonation.started',
        'entity' => 'tenant',
        'entity_id' => $tenant->id,
    ]);
});

it('grants the impersonating platform administrator access to tenant pages', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($platformAdmin)
        ->post(route('platform.tenants.manage', $tenant))
        ->assertRedirect(route('dashboard'));

    $this->get(route('receipts'))->assertOk();
    $this->get(route('admin.users'))->assertOk();
    $this->get(route('admin.assignments'))->assertOk();
});

it('rejects impersonation of an inactive tenant', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create(['active' => false]);

    $this->actingAs($platformAdmin)
        ->post(route('platform.tenants.manage', $tenant))
        ->assertForbidden();

    expect(session(Impersonation::KEY))->toBeNull();
});

it('returns not found when impersonating a missing tenant', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin)
        ->post(route('platform.tenants.manage', '0190f000-0000-7000-8000-000000000000'))
        ->assertNotFound();

    expect(session(Impersonation::KEY))->toBeNull();
});

it('ends impersonation and records an audit trail', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($platformAdmin)
        ->post(route('platform.tenants.manage', $tenant))
        ->assertRedirect(route('dashboard'));

    $this->post(route('platform.impersonation.exit'))
        ->assertRedirect(route('admin.tenants'))
        ->assertSessionMissing(Impersonation::KEY);

    $this->assertAuthenticatedAs($platformAdmin);

    expect(app(CurrentTenant::class)->id())->toBeNull();

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'actor_user_id' => $platformAdmin->id,
        'action' => 'platform.impersonation.ended',
        'entity' => 'tenant',
        'entity_id' => $tenant->id,
    ]);
});

it('drops impersonation when the tenant is deactivated mid-session', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($platformAdmin)
        ->post(route('platform.tenants.manage', $tenant))
        ->assertRedirect(route('dashboard'));

    $tenant->update(['active' => false]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSessionMissing(Impersonation::KEY);

    expect(app(CurrentTenant::class)->id())->toBeNull();
});

it('exposes an ephemeral admin membership only for the active tenant', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create();

    expect($platformAdmin->membershipFor($tenant))->toBeNull();

    app(CurrentTenant::class)->set($tenant->id);

    expect($platformAdmin->membershipFor($tenant)?->role)->toBe(TenantRole::Admin);
});
