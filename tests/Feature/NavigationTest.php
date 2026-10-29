<?php

use App\Enums\TenantRole;
use App\Models\User;

it('exposes every operational module and the admin menu to administrators', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Navegación principal')
        ->assertSee('Navegación móvil')
        ->assertSee('Administración')
        ->assertSee(route('portfolio'), false)
        ->assertSee(route('receipts'), false)
        ->assertSee(route('collections.index'), false)
        ->assertSee(route('cash-sessions.index'), false)
        ->assertSee(route('admin.users'), false)
        ->assertSee(route('admin.assignments'), false)
        ->assertSee(route('admin.audit'), false)
        ->assertSee(route('admin.verification-codes'), false);
});

it('hides restricted admin modules from sellers', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);

    $this->actingAs($seller)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('receipts'), false)
        ->assertSee(route('collections.index'), false)
        ->assertDontSee(route('admin.users'), false)
        ->assertDontSee(route('admin.assignments'), false)
        ->assertDontSee(route('admin.audit'), false)
        ->assertDontSee(route('admin.verification-codes'), false)
        ->assertDontSee(route('admin.tenants'), false);
});

it('shows audit and verification modules to supervisors without tenant management', function () {
    $tenant = tenantContext();
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    $this->actingAs($supervisor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.audit'), false)
        ->assertSee(route('admin.verification-codes'), false)
        ->assertDontSee(route('admin.users'), false)
        ->assertDontSee(route('admin.assignments'), false)
        ->assertDontSee(route('admin.tenants'), false);
});

it('shows the companies module to platform administrators', function () {
    $tenant = tenantContext();
    $platformAdmin = memberFor($tenant, TenantRole::Admin, User::factory()->create(['is_platform_admin' => true]));

    $this->actingAs($platformAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.tenants'), false);
});

it('renders breadcrumbs on child pages', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($admin)
        ->get(route('admin.audit'))
        ->assertOk()
        ->assertSee('Ruta de navegación')
        ->assertSee(route('dashboard'), false)
        ->assertSee('Administración');
});
