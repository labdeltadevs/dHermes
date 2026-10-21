<?php

use App\Enums\CashSessionStatus;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\CashSession;
use App\Models\CashSessionItem;
use App\Models\CollectionEvent;
use App\Models\SalesRep;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function cashSessionPageScenario(): array
{
    $tenant = tenantContext(['name' => 'Empresa Arqueos']);
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $seller->update(['name' => 'Vendedor Propio']);

    $otherRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $otherSeller = memberFor($tenant, TenantRole::Seller, salesRep: $otherRep);
    $otherSeller->update(['name' => 'Vendedor Ajeno']);

    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    app(CurrentTenant::class)->set($tenant->id);

    $own = CashSession::factory()->create([
        'seller_user_id' => $seller->id,
        'currency' => 'BOB',
        'notes' => 'ARQUEO-PROPIO',
        'expected_amount' => 100,
    ]);

    $other = CashSession::factory()->create([
        'seller_user_id' => $otherSeller->id,
        'currency' => 'USD',
        'notes' => 'ARQUEO-AJENO',
        'expected_amount' => 200,
    ]);

    return compact('tenant', 'rep', 'seller', 'otherSeller', 'supervisor', 'own', 'other');
}

it('shows only the seller own cash sessions', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-sessions')
        ->assertOk()
        ->assertSee('Vendedor Propio')
        ->assertDontSee('Vendedor Ajeno');
});

it('shows all cash sessions to a supervisor', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::cash-sessions')
        ->assertOk()
        ->assertSee('Vendedor Propio')
        ->assertSee('Vendedor Ajeno');
});

it('forbids outsiders without a membership from listing cash sessions', function () {
    $scenario = cashSessionPageScenario();
    $outsider = User::factory()->create();

    $this->actingAs($outsider);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::cash-sessions')->assertForbidden();
});

it('opens a cash session from the index page', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-sessions')
        ->set('openCurrency', 'usd')
        ->set('openNotes', 'Apertura de prueba')
        ->call('openSession')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cash_sessions', [
        'tenant_id' => $scenario['tenant']->id,
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'USD',
        'notes' => 'Apertura de prueba',
    ]);
});

it('reports a duplicate open currency on the index page', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-sessions')
        ->set('openCurrency', 'BOB')
        ->call('openSession')
        ->assertHasErrors('openCurrency');
});

it('renders the cash session detail', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->assertOk()
        ->assertSee('Vendedor Propio')
        ->assertSee('ARQUEO-PROPIO')
        ->assertSee('Abierto');
});

it('adds an item and submits the cash session', function () {
    $scenario = cashSessionPageScenario();
    $event = CollectionEvent::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'BOB',
        'status' => CollectionStatus::Pending,
        'amount' => 120,
    ]);

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->set('itemPaymentMethod', PaymentMethod::Cash->value)
        ->set('itemAmount', '120')
        ->set('itemCollectionEventId', $event->id)
        ->call('addItem')
        ->assertHasNoErrors()
        ->set('countedAmount', '100')
        ->call('submit')
        ->assertHasNoErrors();

    $session = $scenario['own']->refresh();

    expect($session->status)->toBe(CashSessionStatus::Submitted)
        ->and((float) $session->expected_amount)->toBe(120.0)
        ->and((float) $session->counted_amount)->toBe(100.0)
        ->and((float) $session->difference)->toBe(-20.0);

    $this->assertDatabaseHas('cash_session_items', [
        'cash_session_id' => $scenario['own']->id,
        'collection_event_id' => $event->id,
        'amount' => 120,
    ]);
});

it('approves and rejects submitted cash sessions as a supervisor', function () {
    $scenario = cashSessionPageScenario();

    $submitted = CashSession::factory()->submitted()->create([
        'seller_user_id' => $scenario['seller']->id,
        'expected_amount' => 100,
        'counted_amount' => 90,
        'difference' => -10,
    ]);

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::cash-session', ['cashSession' => $submitted])
        ->call('approve')
        ->assertHasNoErrors();

    expect($submitted->refresh()->status)->toBe(CashSessionStatus::Approved)
        ->and($submitted->refresh()->approved_by)->toBe($scenario['supervisor']->id);

    $second = CashSession::factory()->submitted()->create([
        'seller_user_id' => $scenario['seller']->id,
    ]);

    Livewire::test('pages::cash-session', ['cashSession' => $second])
        ->set('rejectReason', 'Recalcular montos')
        ->call('reject')
        ->assertHasNoErrors();

    expect($second->refresh()->status)->toBe(CashSessionStatus::Rejected)
        ->and($second->refresh()->notes)->toBe('Recalcular montos');
});

it('requires a reason to reject a cash session', function () {
    $scenario = cashSessionPageScenario();

    $submitted = CashSession::factory()->submitted()->create([
        'seller_user_id' => $scenario['seller']->id,
    ]);

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::cash-session', ['cashSession' => $submitted])
        ->call('reject')
        ->assertHasErrors('rejectReason');

    expect($submitted->refresh()->status)->toBe(CashSessionStatus::Submitted);
});

it('reopens a rejected cash session from the detail page', function () {
    $scenario = cashSessionPageScenario();

    $rejected = CashSession::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'status' => CashSessionStatus::Rejected,
        'expected_amount' => 100,
        'counted_amount' => 90,
        'difference' => -10,
        'closed_at' => now(),
        'notes' => 'Motivo original',
    ]);

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-session', ['cashSession' => $rejected])
        ->call('reopen')
        ->assertHasNoErrors();

    $rejected->refresh();

    expect($rejected->status)->toBe(CashSessionStatus::Open)
        ->and($rejected->closed_at)->toBeNull()
        ->and($rejected->counted_amount)->toBeNull()
        ->and($rejected->difference)->toBeNull()
        ->and($rejected->notes)->toBe('Motivo original');
});

it('does not show the add item form to a supervisor on an open session', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->assertOk()
        ->assertDontSee('Agregar cobro');
});

it('shows the collection event only once when it is already used', function () {
    $scenario = cashSessionPageScenario();
    $event = CollectionEvent::factory()->create([
        'seller_user_id' => $scenario['seller']->id,
        'currency' => 'BOB',
        'status' => CollectionStatus::Pending,
    ]);

    CashSessionItem::factory()->create([
        'cash_session_id' => $scenario['own']->id,
        'collection_event_id' => $event->id,
    ]);

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->set('itemPaymentMethod', PaymentMethod::Cash->value)
        ->set('itemAmount', '50')
        ->set('itemCollectionEventId', $event->id)
        ->call('addItem')
        ->assertHasErrors('collection_event_id');
});

it('renders the cash session routes with uuid binding', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['seller']);

    $this->get(route('cash-sessions.index'))->assertOk();
    $this->get(route('cash-sessions.show', $scenario['own']))
        ->assertOk()
        ->assertSee('ARQUEO-PROPIO');
});

it('issues a receipt for an approved cash session', function () {
    $scenario = cashSessionPageScenario();
    $scenario['own']->update(['status' => CashSessionStatus::Approved]);

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->call('issueComprobante')
        ->assertHasNoErrors();

    $code = VerificationCode::query()->where('verifiable_id', $scenario['own']->id)->first();

    expect($code)->not->toBeNull()
        ->and($code->tenant_id)->toBe($scenario['tenant']->id);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->call('issueComprobante')
        ->assertRedirect(route('verification.print', $code->code));
});

it('does not issue a receipt for an open cash session', function () {
    $scenario = cashSessionPageScenario();

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::cash-session', ['cashSession' => $scenario['own']])
        ->call('issueComprobante')
        ->assertForbidden();

    expect(VerificationCode::query()->count())->toBe(0);
});
