<?php

use App\Enums\TenantRole;
use App\Models\TenantUser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

it('logs in an active member and loads the tenant automatically', function () {
    $tenant = tenantContext(['name' => 'Empresa Norte']);
    $user = memberFor($tenant, TenantRole::Admin);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Empresa Norte')
        ->assertSessionHas('tenant_id', $tenant->id);
});

it('rejects inactive users', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Seller);
    $user->forceFill(['active' => false])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects users without an active tenant membership', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Seller);

    TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->update(['active' => false]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('renders the authentication screens', function () {
    $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    $this->get(route('password.request'))->assertOk()->assertSee('Recuperar contraseña');
    $this->get(route('password.reset', ['token' => 'token-de-prueba']))
        ->assertOk()
        ->assertSee('Nueva contraseña');
});

it('redirects guests away from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('logs out authenticated users', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('requires a two factor challenge after login when enabled', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);
    $secret = (new Google2FA)->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertSee('Verificación en dos pasos');

    $this->post(route('two-factor.login.store'), [
        'code' => (new Google2FA)->getCurrentOtp($secret),
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('sends a password reset link', function () {
    Notification::fake();

    $tenant = tenantContext();
    $user = memberFor($tenant);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password with a valid token', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nueva-password-123',
        'password_confirmation' => 'nueva-password-123',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nueva-password-123', $user->fresh()->password_hash))->toBeTrue();
});

it('shows the public landing page to guests and redirects members', function () {
    $this->get(route('home'))->assertOk()->assertSee('dHermes');

    $tenant = tenantContext();
    $user = memberFor($tenant);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
