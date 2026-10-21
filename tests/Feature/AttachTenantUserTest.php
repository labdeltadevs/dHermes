<?php

use App\Enums\TenantRole;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

it('attaches an existing user to a tenant as admin', function () {
    $tenant = tenantContext(['code' => 'ORIGEN']);
    $user = memberFor($tenant, TenantRole::Admin);
    $destination = Tenant::factory()->create(['code' => 'DESTINO', 'name' => 'Destino SA']);

    $this->artisan('tenant:attach-user', [
        'tenant' => 'destino',
        'email' => $user->email,
        '--role' => 'admin',
    ])->assertExitCode(0);

    $membership = TenantUser::query()
        ->where('tenant_id', $destination->id)
        ->where('user_id', $user->id)
        ->sole();

    expect($membership->role)->toBe(TenantRole::Admin)
        ->and($membership->active)->toBeTrue()
        ->and($membership->sales_rep_id)->toBeNull();
});

it('attaches sellers to their sales rep', function () {
    $tenant = tenantContext(['code' => 'DESTINO']);
    $salesRep = SalesRep::factory()->create([
        'tenant_id' => $tenant->id,
        'slpcode' => '007',
    ]);
    $user = User::factory()->create();

    $this->artisan('tenant:attach-user', [
        'tenant' => 'DESTINO',
        'email' => $user->email,
        '--role' => 'seller',
        '--sales-rep' => '007',
    ])->assertExitCode(0);

    $membership = TenantUser::query()->where('user_id', $user->id)->sole();

    expect($membership->role)->toBe(TenantRole::Seller)
        ->and($membership->sales_rep_id)->toBe($salesRep->id);
});

it('rejects sellers without a sales rep', function () {
    $tenant = tenantContext(['code' => 'DESTINO']);
    $user = User::factory()->create();

    $this->artisan('tenant:attach-user', [
        'tenant' => 'DESTINO',
        'email' => $user->email,
        '--role' => 'seller',
    ])->assertExitCode(1);

    expect(TenantUser::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('rejects an invalid role', function () {
    $tenant = tenantContext(['code' => 'DESTINO']);
    $user = User::factory()->create();

    $this->artisan('tenant:attach-user', [
        'tenant' => 'DESTINO',
        'email' => $user->email,
        '--role' => 'owner',
    ])->assertExitCode(1);
});
