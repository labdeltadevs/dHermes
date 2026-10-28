<?php

use App\Actions\Collection\AcceptCollection;
use App\Actions\Collection\RegisterCollection;
use App\Actions\Collection\RejectCollection;
use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
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

it('accepts a pending collection with an ERP DocNum as supervisor', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);
    $accepted = app(AcceptCollection::class)->handle($scenario['supervisor'], $event, '100341331', true);

    expect($accepted->status)->toBe(CollectionStatus::Accepted)
        ->and($accepted->erp_docnum)->toBe('100341331')
        ->and($accepted->reviewed_by)->toBe($scenario['supervisor']->id)
        ->and($accepted->reviewed_at)->not->toBeNull()
        ->and($accepted->rejection_reason)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'collection.accepted')->count())->toBe(1);
});

it('requires an ERP DocNum to accept a collection', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(AcceptCollection::class)->handle($scenario['supervisor'], $event, '', true))
        ->toThrow(ValidationException::class);

    expect($event->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('rejects a non numeric ERP DocNum', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(AcceptCollection::class)->handle($scenario['supervisor'], $event, 'RCT2-123', true))
        ->toThrow(ValidationException::class);

    expect($event->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('rejects an ERP DocNum already used in the tenant', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $first = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());
    $second = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload(['amount' => 300]));

    $this->actingAs($scenario['supervisor']);
    app(AcceptCollection::class)->handle($scenario['supervisor'], $first, '100341331', true);

    expect(fn () => app(AcceptCollection::class)->handle($scenario['supervisor'], $second, '100341331', true))
        ->toThrow(ValidationException::class);

    expect($second->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('allows the same ERP DocNum in another tenant', function () {
    $firstScenario = collectionScenario();
    $this->actingAs($firstScenario['seller']);
    $first = app(RegisterCollection::class)->handle($firstScenario['seller'], $firstScenario['invoice'], collectionPayload());
    $this->actingAs($firstScenario['supervisor']);
    app(AcceptCollection::class)->handle($firstScenario['supervisor'], $first, '100341331', true);

    $secondTenant = tenantContext();
    $secondRep = SalesRep::factory()->create(['tenant_id' => $secondTenant->id]);
    $secondSeller = memberFor($secondTenant, TenantRole::Seller, salesRep: $secondRep);
    $secondSupervisor = memberFor($secondTenant, TenantRole::Supervisor);
    $secondCustomer = Customer::factory()->create(['tenant_id' => $secondTenant->id, 'sales_rep_id' => $secondRep->id]);
    $secondInvoice = Invoice::factory()->create([
        'customer_id' => $secondCustomer->id,
        'sales_rep_id' => $secondRep->id,
        'total' => 1000,
        'balance' => 1000,
    ]);

    $this->actingAs($secondSeller);
    $second = app(RegisterCollection::class)->handle($secondSeller, $secondInvoice, collectionPayload());

    $this->actingAs($secondSupervisor);
    $accepted = app(AcceptCollection::class)->handle($secondSupervisor, $second, '100341331', true);

    expect($accepted->status)->toBe(CollectionStatus::Accepted)
        ->and($accepted->tenant_id)->toBe($secondTenant->id)
        ->and($first->refresh()->tenant_id)->toBe($firstScenario['tenant']->id);
});

it('requires confirmation that the funds were received', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(AcceptCollection::class)->handle($scenario['supervisor'], $event, '100341331', false))
        ->toThrow(ValidationException::class);

    expect($event->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('does not accept a collection that is no longer pending', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());

    $this->actingAs($scenario['supervisor']);
    app(AcceptCollection::class)->handle($scenario['supervisor'], $event, '100341331', true);

    expect(fn () => app(AcceptCollection::class)->handle($scenario['supervisor'], $event->refresh(), '100341332', true))
        ->toThrow(ValidationException::class);
});

it('rejects a collection with a reason', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        collectionPayload(),
    );

    $this->actingAs($scenario['supervisor']);
    $rejected = app(RejectCollection::class)->handle($scenario['supervisor'], $event, 'Sin comprobante');

    expect($rejected->status)->toBe(CollectionStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('Sin comprobante')
        ->and($rejected->reviewed_by)->toBe($scenario['supervisor']->id)
        ->and($rejected->reviewed_at)->not->toBeNull()
        ->and($rejected->erp_docnum)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'collection.rejected')->first()->details)
        ->toMatchArray(['reason' => 'Sin comprobante']);
});

it('requires a reason to reject a collection', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());

    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(RejectCollection::class)->handle($scenario['supervisor'], $event, ''))
        ->toThrow(ValidationException::class);

    expect($event->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('does not reject a collection that is no longer pending', function () {
    $scenario = collectionScenario();
    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle($scenario['seller'], $scenario['invoice'], collectionPayload());

    $this->actingAs($scenario['supervisor']);
    app(RejectCollection::class)->handle($scenario['supervisor'], $event, 'Sin comprobante');

    expect(fn () => app(RejectCollection::class)->handle($scenario['supervisor'], $event->refresh(), 'Otra vez'))
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
