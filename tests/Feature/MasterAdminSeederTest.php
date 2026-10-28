<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('seeds the tenant and the master administrator', function () {
    $this->seed();

    $tenant = Tenant::query()->where('code', 'DHERMES')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('dHermes')
        ->and($tenant->timezone)->toBe('America/La_Paz')
        ->and($tenant->local_currency)->toBe('BOB')
        ->and($tenant->active)->toBeTrue();

    $user = User::query()->where('email', 'admin@dhermes.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Administrador Maestro')
        ->and($user->active)->toBeTrue()
        ->and($user->is_platform_admin)->toBeTrue()
        ->and(Hash::check('admin-password-1234', $user->password_hash))->toBeTrue();

    $membership = TenantUser::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role)->toBe(TenantRole::Admin)
        ->and($membership->active)->toBeTrue();

    expect(Auth::attempt([
        'email' => 'admin@dhermes.local',
        'password' => 'admin-password-1234',
    ]))->toBeTrue();
});

it('is idempotent and does not duplicate the bootstrap records', function () {
    $this->seed();
    $this->seed();

    expect(Tenant::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(1)
        ->and(TenantUser::query()->withoutGlobalScope('tenant')->count())->toBe(1);
});

it('does not require external SAP data', function () {
    $this->seed();

    expect(DB::table('invoices')->count())->toBe(0)
        ->and(DB::table('receipts')->count())->toBe(0)
        ->and(DB::table('collection_events')->count())->toBe(0);
});
