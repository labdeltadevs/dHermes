<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;

function multiTenantUser(): array
{
    $first = tenantContext(['name' => 'Alfa Uno']);
    $user = memberFor($first, TenantRole::Admin);

    $second = Tenant::factory()->create(['name' => 'Beta Dos']);

    TenantUser::factory()->create([
        'tenant_id' => $second->id,
        'user_id' => $user->id,
        'role' => TenantRole::Admin,
        'active' => true,
    ]);

    return compact('first', 'second', 'user');
}

it('uses the selected tenant from the session when it is an active membership', function () {
    $scenario = multiTenantUser();

    $this->actingAs($scenario['user'])
        ->withSession(['tenant_id' => $scenario['second']->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Beta Dos')
        ->assertSessionHas('tenant_id', $scenario['second']->id);
});

it('ignores a selected tenant without an active membership', function () {
    $scenario = multiTenantUser();
    $foreign = Tenant::factory()->create(['name' => 'Gamma Tres']);

    $this->actingAs($scenario['user'])
        ->withSession(['tenant_id' => $foreign->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Alfa Uno')
        ->assertSessionHas('tenant_id', $scenario['first']->id);
});

it('switches tenants through the switch route', function () {
    $scenario = multiTenantUser();

    $this->actingAs($scenario['user'])
        ->post(route('tenant.switch'), ['tenant_id' => $scenario['second']->id])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('tenant_id', $scenario['second']->id);
});

it('rejects switching to a tenant without an active membership', function () {
    $scenario = multiTenantUser();
    $foreign = Tenant::factory()->create();

    $this->actingAs($scenario['user'])
        ->withSession([])
        ->post(route('tenant.switch'), ['tenant_id' => $foreign->id])
        ->assertStatus(403);

    expect(session('tenant_id'))->toBe($scenario['first']->id);
});

it('redirects guests away from the tenant switch route', function () {
    $scenario = multiTenantUser();

    $this->post(route('tenant.switch'), ['tenant_id' => $scenario['second']->id])
        ->assertRedirect(route('login'));
});

it('renders the tenant switcher for users with multiple memberships', function () {
    $scenario = multiTenantUser();

    $this->actingAs($scenario['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Alfa Uno')
        ->assertSee('Beta Dos');
});
