<?php

use App\Actions\Cash\AddCashSessionItem;
use App\Actions\Cash\ApproveCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\ReopenCashSession;
use App\Actions\Cash\SubmitCashSession;
use App\Enums\CashSessionStatus;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CashSession;
use App\Models\CashSessionItem;
use App\Models\CollectionEvent;
use App\Models\SalesRep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

function cashScenario(): array
{
    $tenant = tenantContext();
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    return compact('tenant', 'rep', 'seller', 'supervisor');
}

it('opens a cash session for the seller', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);

    expect($session->status)->toBe(CashSessionStatus::Open)
        ->and($session->seller_user_id)->toBe($scenario['seller']->id)
        ->and($session->tenant_id)->toBe($scenario['tenant']->id)
        ->and(AuditEvent::query()->where('action', 'cash_session.opened')->count())->toBe(1);
});

it('does not allow supervisors to open cash sessions', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(OpenCashSession::class)->handle($scenario['supervisor'], ['currency' => 'BOB']))
        ->toThrow(AuthorizationException::class);
});

it('adds items and recalculates the expected amount', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);

    app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'payment_method' => PaymentMethod::Cash,
        'amount' => 100,
    ]);
    app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'payment_method' => PaymentMethod::Transfer,
        'amount' => 50,
        'reference' => 'TRX-1',
    ]);

    expect(CashSessionItem::query()->count())->toBe(2)
        ->and((float) $session->refresh()->expected_amount)->toBe(150.0)
        ->and(AuditEvent::query()->where('action', 'cash_session.item_added')->count())->toBe(2);
});

it('submits a session with a counted difference', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);

    app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'payment_method' => PaymentMethod::Cash,
        'amount' => 100,
    ]);

    $submitted = app(SubmitCashSession::class)->handle($scenario['seller'], $session, [
        'counted_amount' => 90,
        'notes' => 'Faltante',
    ]);

    expect($submitted->status)->toBe(CashSessionStatus::Submitted)
        ->and((float) $submitted->expected_amount)->toBe(100.0)
        ->and((float) $submitted->counted_amount)->toBe(90.0)
        ->and((float) $submitted->difference)->toBe(-10.0)
        ->and($submitted->closed_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'cash_session.submitted')->count())->toBe(1);
});

it('prevents adding items after submission', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    $session->update(['status' => CashSessionStatus::Submitted]);

    expect(fn () => app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'payment_method' => PaymentMethod::Cash,
        'amount' => 100,
    ]))->toThrow(AuthorizationException::class);
});

it('allows only supervisors to approve a submitted session', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    app(SubmitCashSession::class)->handle($scenario['seller'], $session, ['counted_amount' => 0]);

    expect(fn () => app(ApproveCashSession::class)->handle($scenario['seller'], $session->refresh()))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($scenario['supervisor']);
    $approved = app(ApproveCashSession::class)->handle($scenario['supervisor'], $session->refresh());

    expect($approved->status)->toBe(CashSessionStatus::Approved)
        ->and($approved->approved_by)->toBe($scenario['supervisor']->id)
        ->and($approved->approved_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'cash_session.approved')->count())->toBe(1);
});

it('rejects a submitted session with a reason', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    app(SubmitCashSession::class)->handle($scenario['seller'], $session, ['counted_amount' => 0]);

    $this->actingAs($scenario['supervisor']);
    $rejected = app(ApproveCashSession::class)->reject($scenario['supervisor'], $session->refresh(), 'Recalcular');

    expect($rejected->status)->toBe(CashSessionStatus::Rejected)
        ->and($rejected->notes)->toBe('Recalcular')
        ->and(AuditEvent::query()->where('action', 'cash_session.rejected')->count())->toBe(1);
});

it('requires an open session to submit', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    $session->update(['status' => CashSessionStatus::Approved]);

    expect(fn () => app(SubmitCashSession::class)->handle($scenario['seller'], $session, ['counted_amount' => 0]))
        ->toThrow(AuthorizationException::class);
});

it('keeps cash sessions scoped by tenant', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);

    expect(CashSession::query()->count())->toBe(1);

    $otherTenant = tenantContext();
    expect(CashSession::query()->count())->toBe(0)
        ->and(CashSession::withoutGlobalScope('tenant')->count())->toBe(1);
});

it('rejects a second open session in the same currency', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'bob']);

    expect(fn () => app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']))
        ->toThrow(ValidationException::class)
        ->and(CashSession::query()->count())->toBe(1);
});

it('allows open sessions in different currencies', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    $second = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'usd']);

    expect($second->currency)->toBe('USD')
        ->and($second->status)->toBe(CashSessionStatus::Open)
        ->and(CashSession::query()->count())->toBe(2);
});

it('rejects collection events that do not belong to the session seller', function () {
    $scenario = cashScenario();
    $otherRep = SalesRep::factory()->create(['tenant_id' => $scenario['tenant']->id]);
    $otherSeller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $otherRep);
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    $event = CollectionEvent::factory()->create([
        'seller_user_id' => $otherSeller->id,
        'currency' => 'BOB',
        'status' => CollectionStatus::Pending,
    ]);

    expect(fn () => app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'collection_event_id' => $event->id,
        'payment_method' => PaymentMethod::Cash,
        'amount' => 50,
    ]))->toThrow(ValidationException::class);
});

it('rejects collection events in another currency', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    $event = CollectionEvent::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'USD',
        'status' => CollectionStatus::Pending,
    ]);

    expect(fn () => app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'collection_event_id' => $event->id,
        'payment_method' => PaymentMethod::Cash,
        'amount' => 50,
    ]))->toThrow(ValidationException::class);
});

it('rejects collection events already used in another session', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = app(OpenCashSession::class)->handle($scenario['seller'], ['currency' => 'BOB']);
    $event = CollectionEvent::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'BOB',
        'status' => CollectionStatus::Pending,
    ]);

    $otherSession = CashSession::factory()->approved()->create([
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'BOB',
    ]);
    CashSessionItem::factory()->create([
        'cash_session_id' => $otherSession->id,
        'collection_event_id' => $event->id,
    ]);

    expect(fn () => app(AddCashSessionItem::class)->handle($scenario['seller'], $session, [
        'collection_event_id' => $event->id,
        'payment_method' => PaymentMethod::Cash,
        'amount' => 50,
    ]))->toThrow(ValidationException::class);
});

it('enforces a unique collection event per cash session item', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = CashSession::factory()->create(['seller_user_id' => $scenario['seller']->id]);
    $event = CollectionEvent::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'BOB',
    ]);

    CashSessionItem::factory()->create([
        'cash_session_id' => $session->id,
        'collection_event_id' => $event->id,
    ]);

    expect(fn () => CashSessionItem::factory()->create([
        'cash_session_id' => $session->id,
        'collection_event_id' => $event->id,
    ]))->toThrow(QueryException::class);
});

it('reopens a rejected session owned by the seller', function () {
    $scenario = cashScenario();
    $this->actingAs($scenario['seller']);

    $session = CashSession::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'status' => CashSessionStatus::Rejected,
        'expected_amount' => 100,
        'counted_amount' => 90,
        'difference' => -10,
        'closed_at' => now(),
        'notes' => 'Motivo original',
    ]);

    $reopened = app(ReopenCashSession::class)->handle($scenario['seller'], $session);

    expect($reopened->status)->toBe(CashSessionStatus::Open)
        ->and($reopened->closed_at)->toBeNull()
        ->and($reopened->counted_amount)->toBeNull()
        ->and($reopened->difference)->toBeNull()
        ->and((float) $reopened->expected_amount)->toBe(100.0)
        ->and($reopened->notes)->toBe('Motivo original')
        ->and(AuditEvent::query()->where('action', 'cash_session.reopened')->count())->toBe(1);
});

it('does not allow another seller to reopen a session', function () {
    $scenario = cashScenario();
    $otherRep = SalesRep::factory()->create(['tenant_id' => $scenario['tenant']->id]);
    $otherSeller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $otherRep);
    $this->actingAs($otherSeller);

    $session = CashSession::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'status' => CashSessionStatus::Rejected,
    ]);

    expect(fn () => app(ReopenCashSession::class)->handle($otherSeller, $session))
        ->toThrow(AuthorizationException::class);
});
