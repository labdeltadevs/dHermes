<?php

use App\Enums\TenantRole;

it('ships a valid installable manifest with existing icons', function () {
    $path = public_path('manifest.webmanifest');

    expect($path)->toBeFile();

    $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toContain('dHermes')
        ->short_name->toBe('dHermes')
        ->start_url->toBe('/dashboard')
        ->scope->toBe('/')
        ->display->toBe('standalone')
        ->background_color->toBe('#ffffff')
        ->theme_color->toBe('#ffffff');

    expect($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('provides a branded offline fallback', function () {
    $offline = file_get_contents(public_path('offline.html'));

    expect($offline)
        ->toContain('Sin conexión')
        ->toContain('dHermes');
});

it('never precaches authenticated pages', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain("request.method !== 'GET'")
        ->toContain("request.mode === 'navigate'")
        ->toContain("url.pathname.startsWith('/livewire/')")
        ->toContain("url.pathname.startsWith('/api/')")
        ->toContain('/offline.html')
        ->not->toContain("'/dashboard'")
        ->not->toContain("'/admin/usuarios'")
        ->not->toContain("'/user/profile'");
});

it('exposes pwa metadata and mobile navigation to authenticated users', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('manifest.webmanifest')
        ->assertSee('name="theme-color"', false)
        ->assertSee('apple-touch-icon')
        ->assertSee('data-pwa-install', false)
        ->assertSee('data-pwa-update-banner', false)
        ->assertSee('Navegación móvil')
        ->assertSee('Usuarios')
        ->assertSee('Perfil');
});

it('exposes pwa metadata on the login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('manifest.webmanifest')
        ->assertSee('name="theme-color"', false);
});
