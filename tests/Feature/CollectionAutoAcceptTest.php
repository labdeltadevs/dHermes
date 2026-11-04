<?php

use App\Actions\Collection\CancelCollection;
use App\Actions\Collection\RegisterCollection;
use App\Actions\Collection\RegisterCollectionErpDocnum;
use App\Actions\Collection\RegisterFlashCollection;
use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function autoAcceptScenario(bool $autoAccept = true): array
{
    $tenant = tenantContext(['code' => 'AUTO', 'auto_accept_collections' => $autoAccept]);
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $rep->id,
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'currency' => 'BOB',
        'total' => 1000,
        'balance' => 1000,
    ]);

    return compact('tenant', 'seller', 'supervisor', 'customer', 'invoice');
}

function autoAcceptCollectionPayload(array $overrides = []): array
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

it('accepts a collection automatically when the tenant enables it', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    expect($event->status)->toBe(CollectionStatus::Accepted)
        ->and($event->erp_docnum)->toBeNull()
        ->and($event->reviewed_at)->not->toBeNull()
        ->and($event->reviewed_by)->toBeNull();

    $audit = AuditEvent::query()
        ->where('action', 'collection.created')
        ->latest('created_at')
        ->firstOrFail();

    expect($audit->details['auto_accepted'])->toBeTrue();
});

it('accepts a flash collection automatically when the tenant enables it', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterFlashCollection::class)->handle($scenario['seller'], [
        'amount' => 50,
        'flash_invoice_number' => 'F-AUTO',
        'flash_cardcode' => 'C-AUTO',
        'flash_customer_name' => 'Cliente Auto',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
    ]);

    expect($event->status)->toBe(CollectionStatus::Accepted)
        ->and($event->erp_docnum)->toBeNull();
});

it('keeps collections pending when the tenant does not enable auto acceptance', function () {
    $scenario = autoAcceptScenario(false);
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    expect($event->status)->toBe(CollectionStatus::Pending);
});

it('keeps the available balance reserved for auto accepted collections without DocNum', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(['amount' => 600]),
    );

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(['amount' => 600]),
    ))->toThrow(ValidationException::class);
});

it('shows auto accepted collections in the default report', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    Livewire::test('pages::collection-reports')
        ->assertOk()
        ->assertSee('AUTO-000000001')
        ->assertSee($scenario['invoice']->number);

    expect($event->number_code)->toBe('AUTO-000000001');
});

it('registers the ERP DocNum later on an auto accepted collection', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);

    $updated = app(RegisterCollectionErpDocnum::class)->handle($scenario['supervisor'], $event, '100341331');

    expect($updated->erp_docnum)->toBe('100341331')
        ->and($updated->status)->toBe(CollectionStatus::Accepted);

    $audit = AuditEvent::query()
        ->where('action', 'collection.docnum_registered')
        ->firstOrFail();

    expect($audit->details['erp_docnum'])->toBe('100341331');
});

it('rejects a duplicate ERP DocNum when registering it later', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $first = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(['amount' => 100]),
    );
    $second = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(['amount' => 200]),
    );

    $this->actingAs($scenario['supervisor']);

    app(RegisterCollectionErpDocnum::class)->handle($scenario['supervisor'], $first, '500000001');

    expect(fn () => app(RegisterCollectionErpDocnum::class)->handle($scenario['supervisor'], $second, '500000001'))
        ->toThrow(ValidationException::class);
});

it('denies registering the ERP DocNum to a seller', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    expect(fn () => app(RegisterCollectionErpDocnum::class)->handle($scenario['seller'], $event, '123456'))
        ->toThrow(AuthorizationException::class);
});

it('allows an approver to cancel an auto accepted collection without DocNum', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);

    $cancelled = app(CancelCollection::class)->handle($scenario['supervisor'], $event, 'Registrado por error');

    expect($cancelled->status)->toBe(CollectionStatus::Cancelled);
});

it('does not allow cancelling an auto accepted collection reconciled with the ERP', function () {
    $scenario = autoAcceptScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        autoAcceptCollectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);

    app(RegisterCollectionErpDocnum::class)->handle($scenario['supervisor'], $event, '900000001');

    expect(fn () => app(CancelCollection::class)->handle($scenario['supervisor'], $event->refresh(), 'Motivo'))
        ->toThrow(AuthorizationException::class);
});
