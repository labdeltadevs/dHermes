<?php

use App\Actions\Collection\MatchCollection;
use App\Actions\Collection\RegisterCollection;
use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function collectionScenario(): array
{
    $tenant = tenantContext();
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    $customer = Customer::factory()->create(['sales_rep_id' => $rep->id]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'total' => 1000,
        'balance' => 1000,
    ]);

    return compact('tenant', 'rep', 'seller', 'supervisor', 'customer', 'invoice');
}

function collectionPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 200,
        'currency' => 'BOB',
        'local_amount' => 200,
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'kind' => CollectionKind::Partial,
        'notes' => 'Pago parcial',
    ], $overrides);
}

it('registers a pending collection and writes an audit event', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );

    expect($event->status)->toBe(CollectionStatus::Pending)
        ->and($event->tenant_id)->toBe($scenario['tenant']->id)
        ->and($event->seller_user_id)->toBe($scenario['seller']->id)
        ->and((float) $event->amount)->toBe(200.0)
        ->and(AuditEvent::query()->where('action', 'collection.created')->count())->toBe(1);
});

it('rejects collections above the invoice balance', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(['amount' => 1500]),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('prevents a seller from registering a collection for a foreign customer', function () {
    $scenario = collectionScenario();
    $otherTenant = tenantContext();
    $rep = SalesRep::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherSeller = memberFor($otherTenant, TenantRole::Seller, salesRep: $rep);
    app(CurrentTenant::class)->set($scenario['tenant']->id);
    $this->actingAs($otherSeller);

    expect(fn () => app(RegisterCollection::class)->handle(
        $otherSeller,
        $scenario['invoice'],
        collectionPayload(),
    ))->toThrow(AuthorizationException::class);
});

it('allows a seller to collect an invoice assigned to them even when the customer belongs to another seller', function () {
    $scenario = collectionScenario();
    $otherRep = SalesRep::factory()->create(['tenant_id' => $scenario['tenant']->id]);
    $customer = Customer::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'sales_rep_id' => $otherRep->id,
    ]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'sales_rep_id' => $scenario['rep']->id,
        'total' => 1000,
        'balance' => 1000,
    ]);

    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $invoice,
        collectionPayload(),
    );

    expect($event->invoice_id)->toBe($invoice->id);
});

it('rejects a collection for an invoice assigned to another seller even when the customer belongs to the seller', function () {
    $scenario = collectionScenario();
    $otherRep = SalesRep::factory()->create(['tenant_id' => $scenario['tenant']->id]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $scenario['customer']->id,
        'sales_rep_id' => $otherRep->id,
        'total' => 1000,
        'balance' => 1000,
    ]);

    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $invoice,
        collectionPayload(),
    ))->toThrow(AuthorizationException::class);
});

it('matches a pending collection with a receipt as supervisor', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);
    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['invoice']->id,
        'applied_amount' => 200,
    ]);

    $matched = app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt);

    expect($matched->status)->toBe(CollectionStatus::Matched)
        ->and($matched->matched_receipt_id)->toBe($receipt->id)
        ->and($matched->matched_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'collection.matched')->count())->toBe(1);
});

it('marks a discrepancy with a reason', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);
    $updated = app(MatchCollection::class)->markDiscrepancy($scenario['supervisor'], $event, 'Sin comprobante');

    expect($updated->status)->toBe(CollectionStatus::Discrepancy)
        ->and(AuditEvent::query()->where('action', 'collection.discrepancy')->first()->details)
        ->toMatchArray(['reason' => 'Sin comprobante']);
});

it('does not match an already matched collection', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );
    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['invoice']->id,
        'applied_amount' => 200,
    ]);

    $this->actingAs($scenario['supervisor']);
    app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt);

    expect(fn () => app(MatchCollection::class)->handle($scenario['supervisor'], $event->refresh(), $receipt))
        ->toThrow(ValidationException::class);
});

it('rejects a collection in a different currency than the invoice', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(['currency' => 'USD']),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('rejects a collection dated after today', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(['collected_on' => now()->addDay()->toDateString()]),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('requires a full collection to match the invoice balance', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(['amount' => 900, 'kind' => CollectionKind::Full]),
    ))->toThrow(ValidationException::class);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(['amount' => 1000, 'kind' => CollectionKind::Full]),
    );

    expect((float) $event->amount)->toBe(1000.0)
        ->and($event->kind)->toBe(CollectionKind::Full);
});

it('requires a partial collection to be less than the invoice balance', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(['amount' => 1000, 'kind' => CollectionKind::Partial]),
    ))->toThrow(ValidationException::class);
});

it('rejects matching a cancelled receipt', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );
    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
        'cancelled' => true,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['invoice']->id,
    ]);

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt))
        ->toThrow(ValidationException::class);
});

it('rejects matching a receipt from another tenant', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );

    $otherTenant = tenantContext();
    $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
    $receipt = Receipt::factory()->create([
        'tenant_id' => $otherTenant->id,
        'customer_id' => $otherCustomer->id,
    ]);
    app(CurrentTenant::class)->set($scenario['tenant']->id);
    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt))
        ->toThrow(ValidationException::class);
});

it('rejects matching a receipt from another customer', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );
    $otherCustomer = Customer::factory()->create(['tenant_id' => $scenario['tenant']->id]);
    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $otherCustomer->id,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['invoice']->id,
    ]);

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt))
        ->toThrow(ValidationException::class);
});

it('rejects matching a receipt in another currency', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );
    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
        'currency' => 'USD',
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['invoice']->id,
    ]);

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt))
        ->toThrow(ValidationException::class);
});

it('rejects matching a receipt without an application to the invoice', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );
    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
    ]);

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(MatchCollection::class)->handle($scenario['supervisor'], $event, $receipt))
        ->toThrow(ValidationException::class);
});

it('keeps every tenant identifier intact', function () {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    expect($first->id)->not->toBe($second->id)
        ->and($first->code)->not->toBe($second->code);
});

it('creates users with uuid identifiers', function () {
    $user = User::factory()->create();

    expect($user->id)->toBeString()->toHaveLength(36);
});
