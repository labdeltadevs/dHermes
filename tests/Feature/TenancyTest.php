<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Tenancy\CurrentTenant;

it('assigns the active tenant to new records', function () {
    $tenant = tenantContext();

    $customer = Customer::factory()->create();

    expect($customer->tenant_id)->toBe($tenant->id);
});

it('scopes queries to the active tenant', function () {
    $first = tenantContext();
    $firstCustomer = Customer::factory()->create();

    $second = tenantContext();
    $secondCustomer = Customer::factory()->create();

    expect(Customer::query()->pluck('id')->all())->toBe([$secondCustomer->id])
        ->and(Customer::withoutGlobalScope('tenant')->count())->toBe(2);

    app(CurrentTenant::class)->set($first->id);

    expect(Customer::query()->pluck('id')->all())->toBe([$firstCustomer->id]);
});

it('returns no records without an active tenant', function () {
    tenantContext();
    Customer::factory()->create();

    app(CurrentTenant::class)->clear();

    expect(Customer::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

it('keeps tenants isolated by their identifiers', function () {
    $first = tenantContext();
    $second = tenantContext();

    expect($first->id)->not->toBe($second->id)
        ->and($first->code)->not->toBe($second->code);
});
