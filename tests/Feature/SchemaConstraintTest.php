<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

it('rejects documents that reference a customer from another tenant', function () {
    tenantContext();
    $customer = Customer::factory()->create();

    $otherTenant = tenantContext();

    expect(fn () => Invoice::factory()->create([
        'tenant_id' => $otherTenant->id,
        'customer_id' => $customer->id,
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate tenant codes', function () {
    Tenant::factory()->create(['code' => 'DUP']);

    expect(fn () => Tenant::factory()->create(['code' => 'DUP']))
        ->toThrow(QueryException::class);
});

it('rejects duplicate invoice source keys within a tenant', function () {
    tenantContext();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'docentry' => '9001']);

    expect(fn () => Invoice::factory()->create([
        'customer_id' => $customer->id,
        'docentry' => '9001',
    ]))->toThrow(QueryException::class);
});
