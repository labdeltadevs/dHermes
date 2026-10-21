<?php

use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('updates the authenticated user profile', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($user);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::profile')
        ->set('name', 'Nombre Actualizado')
        ->set('email', 'actualizado@dhermes.test')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Nombre Actualizado')
        ->email->toBe('actualizado@dhermes.test');
});

it('requires the current password to change the password', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($user);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::profile')
        ->set('currentPassword', 'incorrecta')
        ->set('password', 'nueva-password-123')
        ->set('password_confirmation', 'nueva-password-123')
        ->call('updatePassword')
        ->assertHasErrors('currentPassword');

    expect(Hash::check('password', $user->fresh()->password_hash))->toBeTrue();
});

it('changes the password with the correct current password', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($user);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::profile')
        ->set('currentPassword', 'password')
        ->set('password', 'nueva-password-123')
        ->set('password_confirmation', 'nueva-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('nueva-password-123', $user->fresh()->password_hash))->toBeTrue();
});

it('enables and confirms two factor authentication', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($user);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::profile')
        ->set('currentPassword', 'password')
        ->call('enableTwoFactorAuthentication')
        ->assertHasNoErrors();

    $user->refresh();
    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
    $code = (new Google2FA)->getCurrentOtp($secret);

    Livewire::test('pages::profile')
        ->set('twoFactorCode', $code)
        ->call('confirmTwoFactorAuthentication')
        ->assertHasNoErrors();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});
