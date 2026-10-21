<?php

use App\Enums\TenantRole;
use App\Models\SalesRep;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

it('lets an admin create a user in the current tenant', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->call('create')
        ->set('name', 'Nueva Supervisora')
        ->set('email', 'supervisora@dhermes.test')
        ->set('role', TenantRole::Supervisor->value)
        ->set('password', 'password123')
        ->set('active', true)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'supervisora@dhermes.test')->firstOrFail();

    expect($user->active)->toBeTrue();

    $this->assertDatabaseHas('tenant_users', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantRole::Supervisor->value,
        'active' => true,
    ]);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'user.created',
        'entity' => 'user',
        'entity_id' => $user->id,
    ]);
});

it('lets an admin update a user and audits the change', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $salesRep = SalesRep::factory()->create();
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $salesRep);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->call('edit', $seller->id)
        ->set('name', 'Vendedor Actualizado')
        ->call('save')
        ->assertHasNoErrors();

    expect($seller->fresh()->name)->toBe('Vendedor Actualizado');

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'user.updated',
        'entity_id' => $seller->id,
    ]);
});

it('prevents an admin from deactivating their own account', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->call('toggleActive', $admin->id)
        ->assertHasErrors('status');

    expect($admin->fresh()->active)->toBeTrue();
});

it('prevents an admin from removing their own admin role', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->call('edit', $admin->id)
        ->set('role', TenantRole::Supervisor->value)
        ->call('save')
        ->assertHasErrors('role');
});

it('forbids non-admins from managing users', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);

    $this->actingAs($seller)
        ->get(route('admin.users'))
        ->assertForbidden();
});

it('only lists users from the current tenant', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $otherTenant = tenantContext(['code' => 'OTRA']);
    $otherUser = memberFor($otherTenant, TenantRole::Admin);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->assertSee($admin->email)
        ->assertDontSee($otherUser->email);
});

it('deactivates a user and writes an audit event', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $seller = memberFor($tenant, TenantRole::Seller);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->call('toggleActive', $seller->id)
        ->assertHasNoErrors();

    expect($seller->fresh()->active)->toBeFalse();

    $membership = TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $seller->id)
        ->firstOrFail();

    expect($membership->active)->toBeFalse();

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'user.deactivated',
        'entity_id' => $seller->id,
    ]);
});

it('paginates users with numbered page links', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    foreach (range(1, 26) as $index) {
        memberFor($tenant, TenantRole::Supervisor);
    }

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.users')
        ->assertSee('Mostrando 1 a 25 de 27 resultados')
        ->assertSeeHtml('aria-label="Ir a la página 2"')
        ->call('gotoPage', 2)
        ->assertSee('Mostrando 26 a 27 de 27 resultados')
        ->assertSeeHtml('aria-current="page"');
});
