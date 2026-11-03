<?php

use App\Actions\Collection\RegisterCollection;
use App\Actions\Collection\RegisterFlashCollection;
use App\Enums\CollectionKind;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Support\Verification\IssueVerificationCode;
use Livewire\Livewire;

function collectionNumberScenario(array $tenantAttributes = []): array
{
    $tenant = tenantContext($tenantAttributes);
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $rep->id,
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'currency' => 'BOB',
        'total' => 2000,
        'balance' => 2000,
    ]);

    return compact('tenant', 'rep', 'seller', 'customer', 'invoice');
}

function numberCollectionPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 100,
        'currency' => 'BOB',
        'local_amount' => 100,
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'kind' => CollectionKind::Partial,
    ], $overrides);
}

function numberFlashPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 50,
        'flash_invoice_number' => 'FAC-NUM',
        'flash_cardcode' => 'CLI-NUM',
        'flash_customer_name' => 'Cliente Numeración',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
    ], $overrides);
}

it('assigns consecutive collection numbers per tenant including flash collections', function () {
    $scenario = collectionNumberScenario(['code' => 'NUM']);
    $this->actingAs($scenario['seller']);

    $first = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        numberCollectionPayload(),
    );

    $second = app(RegisterFlashCollection::class)->handle(
        $scenario['seller'],
        numberFlashPayload(),
    );

    expect($first->number)->toBe(1)
        ->and($first->number_code)->toBe('NUM-000000001')
        ->and($second->number)->toBe(2)
        ->and($second->number_code)->toBe('NUM-000000002');
});

it('restarts the sequence for each tenant using its own prefix', function () {
    $first = collectionNumberScenario(['code' => 'ALFA']);
    $this->actingAs($first['seller']);

    app(RegisterCollection::class)->handle($first['seller'], $first['invoice'], numberCollectionPayload());

    $second = collectionNumberScenario(['code' => 'BETA']);
    $this->actingAs($second['seller']);

    $event = app(RegisterCollection::class)->handle(
        $second['seller'],
        $second['invoice'],
        numberCollectionPayload(),
    );

    expect($event->number)->toBe(1)
        ->and($event->number_code)->toBe('BETA-000000001');
});

it('shows the collection number and finds collections by it in the list', function () {
    $scenario = collectionNumberScenario(['code' => 'NUM']);
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        numberCollectionPayload(),
    );

    Livewire::test('pages::collections')
        ->assertSee('NUM-000000001')
        ->set('search', 'NUM-000000001')
        ->assertSee('NUM-000000001');
});

it('prints the collection number on the verification receipt', function () {
    $scenario = collectionNumberScenario(['code' => 'NUM']);
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        numberCollectionPayload(),
    );

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $event);

    $this->withoutVite()
        ->get(route('verification.print', $code->code))
        ->assertOk()
        ->assertSee('N° COBRANZA:')
        ->assertSee('NUM-000000001');
});
