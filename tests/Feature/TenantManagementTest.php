<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets a platform administrator without memberships into the panel', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.tenants'))
        ->assertOk();
});

it('forbids tenant administrators from managing tenants', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin)
        ->get(route('admin.tenants'))
        ->assertForbidden();
});

it('creates a tenant with its initial administrator', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.tenants')
        ->call('create')
        ->set('code', 'acme')
        ->set('name', 'ACME SRL')
        ->set('taxId', '123456789')
        ->set('timezone', 'America/La_Paz')
        ->set('localCurrency', 'bob')
        ->set('active', true)
        ->set('adminName', 'Administradora ACME')
        ->set('adminEmail', 'admin@acme.test')
        ->set('adminPassword', 'password123')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = Tenant::query()->where('code', 'ACME')->firstOrFail();

    expect($tenant->name)->toBe('ACME SRL')
        ->and($tenant->local_currency)->toBe('BOB')
        ->and($tenant->active)->toBeTrue();

    $admin = User::query()->where('email', 'admin@acme.test')->firstOrFail();

    expect($admin->active)->toBeTrue()
        ->and(Hash::check('password123', $admin->password_hash))->toBeTrue();

    $this->assertDatabaseHas('tenant_users', [
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'role' => TenantRole::Admin->value,
        'active' => true,
    ]);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'tenant.created',
        'entity' => 'tenant',
        'entity_id' => $tenant->id,
    ]);

    expect(TenantUser::query()->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->count())->toBe(1);
});

it('attaches an existing user as administrator without changing the password', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $existing = User::factory()->create([
        'email' => 'existing@acme.test',
        'password_hash' => Hash::make('original-password'),
    ]);

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.tenants')
        ->call('create')
        ->set('code', 'ACME2')
        ->set('name', 'ACME Dos')
        ->set('timezone', 'America/La_Paz')
        ->set('localCurrency', 'BOB')
        ->set('adminEmail', 'existing@acme.test')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = Tenant::query()->where('code', 'ACME2')->firstOrFail();

    expect(Hash::check('original-password', $existing->fresh()->password_hash))->toBeTrue()
        ->and(TenantUser::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $existing->id)
            ->where('role', TenantRole::Admin->value)
            ->exists())->toBeTrue();
});

it('rejects an inactive user as the initial administrator', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    User::factory()->inactive()->create(['email' => 'inactive@acme.test']);

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.tenants')
        ->call('create')
        ->set('code', 'ACME3')
        ->set('name', 'ACME Tres')
        ->set('timezone', 'America/La_Paz')
        ->set('localCurrency', 'BOB')
        ->set('adminEmail', 'inactive@acme.test')
        ->call('save')
        ->assertHasErrors('adminEmail');

    expect(Tenant::query()->where('code', 'ACME3')->exists())->toBeFalse();
});

it('updates a tenant without changing its code', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = Tenant::factory()->create(['code' => 'FIXED']);

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.tenants')
        ->call('edit', $tenant->id)
        ->assertSet('code', 'FIXED')
        ->set('code', 'CHANGED')
        ->set('name', 'Nombre Actualizado')
        ->set('taxId', '987654321')
        ->set('timezone', 'America/Bogota')
        ->set('localCurrency', 'USD')
        ->call('save')
        ->assertHasNoErrors();

    $tenant->refresh();

    expect($tenant->code)->toBe('FIXED')
        ->and($tenant->name)->toBe('Nombre Actualizado')
        ->and($tenant->tax_id)->toBe('987654321')
        ->and($tenant->timezone)->toBe('America/Bogota')
        ->and($tenant->local_currency)->toBe('USD');

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'tenant.updated',
        'entity' => 'tenant',
        'entity_id' => $tenant->id,
    ]);
});

it('does not allow deactivating the tenant in use', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $tenant = tenantContext();

    $this->actingAs($platformAdmin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.tenants')
        ->call('toggleActive', $tenant->id)
        ->assertHasErrors('status');

    expect($tenant->fresh()->active)->toBeTrue();
});

it('activates and deactivates a tenant with an audit trail', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    $current = tenantContext();
    $tenant = Tenant::factory()->create(['active' => true]);

    $this->actingAs($platformAdmin);
    app(CurrentTenant::class)->set($current->id);

    Livewire::test('pages::admin.tenants')
        ->call('toggleActive', $tenant->id)
        ->assertHasNoErrors();

    expect($tenant->fresh()->active)->toBeFalse();

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'tenant.deactivated',
        'entity_id' => $tenant->id,
    ]);

    Livewire::test('pages::admin.tenants')
        ->call('toggleActive', $tenant->id)
        ->assertHasNoErrors();

    expect($tenant->fresh()->active)->toBeTrue();

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'action' => 'tenant.activated',
        'entity_id' => $tenant->id,
    ]);
});

it('validates the tenant data', function (string $field, mixed $value) {
    $platformAdmin = User::factory()->platformAdmin()->create();
    Tenant::factory()->create(['code' => 'TAKEN']);

    $this->actingAs($platformAdmin);

    Livewire::test('pages::admin.tenants')
        ->call('create')
        ->set('code', 'NEW')
        ->set('name', 'Nueva Empresa')
        ->set('timezone', 'America/La_Paz')
        ->set('localCurrency', 'BOB')
        ->set('adminName', 'Administrador')
        ->set('adminEmail', 'admin@nueva.test')
        ->set('adminPassword', 'password123')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);

    expect(Tenant::query()->where('code', 'NEW')->exists())->toBeFalse();
})->with([
    'duplicate code' => ['code', 'TAKEN'],
    'invalid code' => ['code', 'bad code!'],
    'invalid timezone' => ['timezone', 'Mars/Olympus'],
    'invalid currency' => ['localCurrency', 'BO'],
    'invalid email' => ['adminEmail', 'not-an-email'],
]);
