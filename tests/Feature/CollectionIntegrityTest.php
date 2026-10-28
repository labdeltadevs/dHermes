<?php

use App\Actions\Collection\CancelCollection;
use App\Actions\Collection\RegisterCollection;
use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function integrityScenario(): array
{
    $tenant = tenantContext();
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $admin = memberFor($tenant, TenantRole::Admin);
    $otherSeller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);

    $customer = Customer::factory()->create(['sales_rep_id' => $rep->id]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'currency' => 'BOB',
        'total' => 1000,
        'balance' => 1000,
    ]);

    return compact('tenant', 'rep', 'seller', 'admin', 'otherSeller', 'customer', 'invoice');
}

function integrityPayload(): array
{
    return [
        'amount' => 700,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'kind' => CollectionKind::Partial,
    ];
}

it('reserves the invoice balance against pending collections', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(['amount' => 700]),
    );

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    ))->toThrow(ValidationException::class);
});

it('rejects a full collection while a partial one is pending', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    );

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(['amount' => 1000, 'kind' => CollectionKind::Full]),
    ))->toThrow(ValidationException::class);
});

it('frees the reserved balance when the pending collection is cancelled', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    );

    app(CancelCollection::class)->handle($scenario['seller'], $event, 'Monto informado con error.');

    expect($event->refresh()->status->value)->toBe('cancelled')
        ->and(fn () => app(RegisterCollection::class)->handle(
            $scenario['seller'],
            $scenario['invoice'],
            integrityPayload(),
        ))->not->toThrow(ValidationException::class);
});

it('allows the registering member to cancel their own pending collection and writes an audit event', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    );

    app(CancelCollection::class)->handle($scenario['seller'], $event, 'Informado por error.');

    expect(AuditEvent::query()->where('action', 'collection.cancelled')->count())->toBe(1);
});

it('allows an admin to cancel a pending collection of any seller', function () {
    $scenario = integrityScenario();

    $this->actingAs($scenario['seller']);
    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    );

    $this->actingAs($scenario['admin']);
    app(CancelCollection::class)->handle($scenario['admin'], $event, 'Revisado por el administrador.');

    expect($event->refresh()->status->value)->toBe('cancelled')
        ->and(AuditEvent::query()->where('action', 'collection.cancelled')->count())->toBe(1);
});

it('rejects cancellation from another seller', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['otherSeller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    );

    expect(fn () => app(CancelCollection::class)->handle(
        $scenario['otherSeller'],
        $event,
        'Intento espurio.',
    ))->toThrow(AuthorizationException::class);
});

it('rejects a seller cancellation of a collection from a previous day', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['seller']);

    $event = CollectionEvent::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'invoice_id' => $scenario['invoice']->id,
        'seller_user_id' => $scenario['seller']->id,
        'amount' => 100,
        'currency' => 'BOB',
        'collected_on' => now()->subDay()->toDateString(),
        'status' => CollectionStatus::Pending,
    ]);

    expect(fn () => app(CancelCollection::class)->handle(
        $scenario['seller'],
        $event,
        'Intento fuera de plazo.',
    ))->toThrow(AuthorizationException::class);

    expect($event->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('allows an admin to cancel a collection from a previous day', function () {
    $scenario = integrityScenario();

    $event = CollectionEvent::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'invoice_id' => $scenario['invoice']->id,
        'seller_user_id' => $scenario['seller']->id,
        'amount' => 100,
        'currency' => 'BOB',
        'collected_on' => now()->subDay()->toDateString(),
        'status' => CollectionStatus::Pending,
    ]);

    $this->actingAs($scenario['admin']);

    app(CancelCollection::class)->handle($scenario['admin'], $event, 'Anulación administrativa.');

    expect($event->refresh()->status)->toBe(CollectionStatus::Cancelled);
});

it('rejects cancellation of matched or cancelled collections', function () {
    $scenario = integrityScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        integrityPayload(),
    );

    app(CancelCollection::class)->handle($scenario['seller'], $event, 'Primera anulación.');

    expect(fn () => app(CancelCollection::class)->handle(
        $scenario['seller'],
        $event->refresh(),
        'Segunda anulación.',
    ))->toThrow(AuthorizationException::class);

    expect(fn () => app(CancelCollection::class)->handle(
        $scenario['seller'],
        $event->refresh(),
        '',
    ))->toThrow(AuthorizationException::class);
});
