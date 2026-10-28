<?php

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Models\VerificationCode;
use Livewire\Livewire;

function collectionPageScenario(): array
{
    $tenant = tenantContext(['name' => 'Empresa Cobranzas']);

    $repOne = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $repTwo = SalesRep::factory()->create(['tenant_id' => $tenant->id]);

    $sellerOne = memberFor($tenant, TenantRole::Seller, salesRep: $repOne);
    $sellerTwo = memberFor($tenant, TenantRole::Seller, salesRep: $repTwo);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    $customerOne = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $repOne->id,
        'name' => 'Cliente Uno',
    ]);
    $customerTwo = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $repTwo->id,
        'name' => 'Cliente Dos',
    ]);

    $invoiceOne = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customerOne->id,
        'sales_rep_id' => $repOne->id,
        'currency' => 'BOB',
        'total' => 1000,
        'balance' => 1000,
    ]);
    $invoiceTwo = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customerTwo->id,
        'sales_rep_id' => $repTwo->id,
        'currency' => 'USD',
        'total' => 2000,
        'balance' => 2000,
    ]);

    $eventOne = CollectionEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoiceOne->id,
        'seller_user_id' => $sellerOne->id,
        'amount' => 250,
        'currency' => 'BOB',
        'collected_on' => now()->subDay()->toDateString(),
        'status' => CollectionStatus::Pending,
    ]);
    $eventTwo = CollectionEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoiceTwo->id,
        'seller_user_id' => $sellerTwo->id,
        'amount' => 500,
        'currency' => 'USD',
        'collected_on' => now()->subDays(3)->toDateString(),
        'status' => CollectionStatus::Rejected,
        'rejection_reason' => 'Datos inconsistentes',
    ]);

    return compact(
        'tenant',
        'repOne',
        'sellerOne',
        'sellerTwo',
        'supervisor',
        'customerOne',
        'customerTwo',
        'invoiceOne',
        'invoiceTwo',
        'eventOne',
        'eventTwo',
    );
}

it('shows only the seller own collection events', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->assertOk()
        ->assertSee($scenario['invoiceOne']->number)
        ->assertDontSee($scenario['invoiceTwo']->number);
});

it('shows all collection events to a supervisor', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->assertOk()
        ->assertSee($scenario['invoiceOne']->number)
        ->assertSee($scenario['invoiceTwo']->number);
});

it('filters collection events by search, status, seller, currency and date', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->assertSee($scenario['invoiceOne']->number)
        ->assertSee($scenario['invoiceTwo']->number)
        ->set('search', 'Cliente Dos')
        ->assertSee($scenario['invoiceTwo']->number)
        ->assertDontSee($scenario['invoiceOne']->number)
        ->set('search', '')
        ->set('status', CollectionStatus::Rejected->value)
        ->assertSee($scenario['invoiceTwo']->number)
        ->assertDontSee($scenario['invoiceOne']->number)
        ->set('status', '')
        ->set('sellerId', $scenario['sellerOne']->id)
        ->assertSee($scenario['invoiceOne']->number)
        ->assertDontSee($scenario['invoiceTwo']->number)
        ->set('sellerId', '')
        ->set('currency', 'USD')
        ->assertSee($scenario['invoiceTwo']->number)
        ->assertDontSee($scenario['invoiceOne']->number)
        ->set('currency', '')
        ->set('from', now()->subDays(2)->toDateString())
        ->assertSee($scenario['invoiceOne']->number)
        ->assertDontSee($scenario['invoiceTwo']->number);
});

it('paginates collection events with numbered page links', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    CollectionEvent::factory()->count(30)->create([
        'tenant_id' => $tenant->id,
        'seller_user_id' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::collections')
        ->assertSee('Mostrando 1 a 25 de 30 resultados')
        ->assertSeeHtml('aria-label="Ir a la página 2"')
        ->call('gotoPage', 2)
        ->assertSee('Mostrando 26 a 30 de 30 resultados')
        ->assertSeeHtml('aria-current="page"');
});

it('registers a collection from the create page', function () {
    $tenant = tenantContext();
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
        'total' => 1000,
        'balance' => 1000,
    ]);

    $this->actingAs($seller);

    Livewire::test('pages::collection-create', ['invoice' => $invoice->id])
        ->assertOk()
        ->assertSee($invoice->number)
        ->set('amount', '400')
        ->set('kind', CollectionKind::Partial->value)
        ->set('paymentMethod', PaymentMethod::Transfer->value)
        ->set('collectedOn', now()->toDateString())
        ->set('voucherCode', 'V-123')
        ->set('notes', 'Abono')
        ->set('confirmed', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('collections.index'));

    $event = CollectionEvent::query()->where('invoice_id', $invoice->id)->first();

    expect($event)->not->toBeNull()
        ->and((float) $event->amount)->toBe(400.0)
        ->and($event->currency)->toBe('BOB')
        ->and($event->kind)->toBe(CollectionKind::Partial)
        ->and($event->status)->toBe(CollectionStatus::Pending)
        ->and($event->seller_user_id)->toBe($seller->id)
        ->and(AuditEvent::query()->where('action', 'collection.created')->count())->toBe(1);
});

it('validates currency, kind and date when creating a collection', function () {
    $tenant = tenantContext();
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
        'total' => 1000,
        'balance' => 1000,
    ]);

    $this->actingAs($seller);

    $component = Livewire::test('pages::collection-create', ['invoice' => $invoice->id])
        ->set('confirmed', true);

    $component->set('currency', 'USD')
        ->set('kind', CollectionKind::Full->value)
        ->set('collectedOn', now()->toDateString())
        ->call('save')
        ->assertHasErrors('currency');

    $component->set('currency', 'BOB')
        ->set('amount', '900')
        ->set('kind', CollectionKind::Full->value)
        ->call('save')
        ->assertHasErrors('amount');

    $component->set('amount', '1000')
        ->set('kind', CollectionKind::Partial->value)
        ->call('save')
        ->assertHasErrors('amount');

    $component->set('kind', CollectionKind::Full->value)
        ->set('collectedOn', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors('collected_on');

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('opens the approval panel for a pending collection', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->assertDontSee('Revisar cobranza')
        ->call('openActionPanel', $scenario['eventOne']->id)
        ->assertSet('pendingActionEventId', $scenario['eventOne']->id)
        ->assertSeeHtml('role="dialog"')
        ->assertSeeHtml('aria-modal="true"')
        ->assertSee('Revisar cobranza')
        ->assertSee($scenario['invoiceOne']->number)
        ->assertSee('Cliente Uno')
        ->assertSee('DocNum ERP')
        ->assertSee('Aceptar cobro')
        ->assertSee('Rechazar cobro')
        ->call('closeActionPanel')
        ->assertSet('pendingActionEventId', '')
        ->assertDontSee('Revisar cobranza');
});

it('opens the cancellation modal for a seller with a collection from today', function () {
    $scenario = collectionPageScenario();
    $scenario['eventOne']->update(['collected_on' => now()->toDateString()]);

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->assertDontSee('Anular cobranza')
        ->call('openCancelPanel', $scenario['eventOne']->id)
        ->assertSet('cancelEventId', $scenario['eventOne']->id)
        ->assertSeeHtml('role="dialog"')
        ->assertSeeHtml('aria-modal="true"')
        ->assertSee('Anular cobranza')
        ->assertSee($scenario['invoiceOne']->number)
        ->assertSee('Cliente Uno')
        ->assertSee('Solo puedes anular cobros registrados el día de hoy.')
        ->call('closeCancelPanel')
        ->assertSet('cancelEventId', '')
        ->assertDontSee('Anular cobranza');
});

it('requires a reason and cancels a same-day collection', function () {
    $scenario = collectionPageScenario();
    $scenario['eventOne']->update(['collected_on' => now()->toDateString()]);

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->call('openCancelPanel', $scenario['eventOne']->id)
        ->call('cancelEvent')
        ->assertHasErrors('cancelReason');

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Pending);

    Livewire::test('pages::collections')
        ->call('openCancelPanel', $scenario['eventOne']->id)
        ->set('cancelReason', 'Monto informado por error')
        ->call('cancelEvent')
        ->assertHasNoErrors()
        ->assertSet('feedback', 'Cobranza anulada.');

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Cancelled);
});

it('hides the cancellation action from sellers for previous-day collections', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->assertDontSee('Anular');
});

it('forbids sellers from opening the cancellation of a previous-day collection', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->call('openCancelPanel', $scenario['eventOne']->id)
        ->assertSet('cancelEventId', '')
        ->assertDontSee('Anular cobranza');
});

it('allows a supervisor to cancel a previous-day collection', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->call('openCancelPanel', $scenario['eventOne']->id)
        ->assertSee('Anular cobranza')
        ->set('cancelReason', 'Revisión administrativa')
        ->call('cancelEvent')
        ->assertHasNoErrors();

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Cancelled);
});

it('accepts a collection with an ERP DocNum and funds confirmation', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->call('openActionPanel', $scenario['eventOne']->id)
        ->set('erpDocnum', '100341331')
        ->set('fundsReceived', true)
        ->call('acceptEvent')
        ->assertHasNoErrors()
        ->assertSet('feedback', 'Cobranza aceptada.');

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Accepted)
        ->and($scenario['eventOne']->erp_docnum)->toBe('100341331')
        ->and($scenario['eventOne']->reviewed_by)->toBe($scenario['supervisor']->id)
        ->and(AuditEvent::query()->where('action', 'collection.accepted')->count())->toBe(1);
});

it('validates the ERP DocNum and funds confirmation before accepting', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->call('openActionPanel', $scenario['eventOne']->id)
        ->call('acceptEvent')
        ->assertHasErrors(['erpDocnum', 'fundsReceived'])
        ->set('erpDocnum', 'RCT2-123')
        ->set('fundsReceived', true)
        ->call('acceptEvent')
        ->assertHasErrors('erpDocnum');

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Pending);
});

it('requires a reason and updates the status when rejecting', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->call('openActionPanel', $scenario['eventOne']->id)
        ->call('rejectEvent')
        ->assertHasErrors('rejectionReason');

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Pending);

    Livewire::test('pages::collections')
        ->call('openActionPanel', $scenario['eventOne']->id)
        ->set('rejectionReason', 'Comprobante no localizado')
        ->call('rejectEvent')
        ->assertHasNoErrors()
        ->assertSet('feedback', 'Cobranza rechazada.');

    expect($scenario['eventOne']->refresh()->status)->toBe(CollectionStatus::Rejected)
        ->and($scenario['eventOne']->rejection_reason)->toBe('Comprobante no localizado')
        ->and(AuditEvent::query()->where('action', 'collection.rejected')->count())->toBe(1);
});

it('issues and redirects to the receipt for an accepted collection', function () {
    $scenario = collectionPageScenario();
    $scenario['eventOne']->update([
        'status' => CollectionStatus::Accepted,
        'erp_docnum' => '100341331',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->call('issueComprobante', $scenario['eventOne']->id)
        ->assertHasNoErrors();

    $code = VerificationCode::query()->where('verifiable_id', $scenario['eventOne']->id)->first();

    expect($code)->not->toBeNull()
        ->and($code->tenant_id)->toBe($scenario['tenant']->id);

    Livewire::test('pages::collections')
        ->call('issueComprobante', $scenario['eventOne']->id)
        ->assertRedirect(route('verification.print', $code->code));
});

it('does not issue a receipt for collections that are not accepted', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->call('issueComprobante', $scenario['eventOne']->id)
        ->assertForbidden();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->call('issueComprobante', $scenario['eventTwo']->id)
        ->assertForbidden();

    expect(VerificationCode::query()->count())->toBe(0);
});

it('opens the create collection modal for sellers', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->assertSee('Nuevo cobro')
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true)
        ->assertSee('Registrar cobro')
        ->assertSee('Selecciona una factura');
});

it('does not show the create button to roles that cannot sell', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->assertDontSee('Nuevo cobro');
});

it('closes the modal and shows feedback after a collection is created', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collections')
        ->call('openCreateModal')
        ->call('onCollectionCreated')
        ->assertSet('showCreateModal', false)
        ->assertSet('feedback', 'Cobro registrado correctamente.')
        ->assertSee('Cobro registrado correctamente.');
});

it('dispatches collection-created when saving from the embedded form', function () {
    $scenario = collectionPageScenario();

    $this->actingAs($scenario['sellerOne']);

    Livewire::test('pages::collection-create', [
        'invoice' => $scenario['invoiceOne']->id,
        'embedded' => true,
    ])
        ->set('amount', '200')
        ->set('kind', CollectionKind::Partial->value)
        ->set('paymentMethod', PaymentMethod::Cash->value)
        ->set('collectedOn', now()->toDateString())
        ->set('confirmed', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('collection-created')
        ->assertNoRedirect();
});
