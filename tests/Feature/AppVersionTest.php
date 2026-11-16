<?php

use App\Enums\TenantRole;

it('serves the service worker with the configured app version', function () {
    config(['app.version' => 'abc1234']);

    $response = $this->get('/sw.js');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/javascript; charset=utf-8');

    expect($response->getContent())
        ->toContain("const CACHE_VERSION = 'dhermes-static-abc1234';");
});

it('shows the app version on the profile page', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);

    config(['app.version' => 'abc1234']);

    $this->actingAs($seller)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Versión: abc1234');
});
