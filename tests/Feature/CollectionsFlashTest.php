<?php

use App\Actions\Collection\AcceptCollection;
use App\Actions\Collection\RegisterFlashCollection;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Support\Verification\IssueVerificationCode;
use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerifyCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function flashScenario(array $tenantAttributes = []): array
{
    $tenant = tenantContext($tenantAttributes);
    $seller = memberFor($tenant, TenantRole::Seller);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    return compact('tenant', 'seller', 'supervisor');
}

/**
 * @return array<string, mixed>
 */
function flashPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 150.50,
        'flash_invoice_number' => 'FAC-1001',
        'flash_cardcode' => 'CLI-001',
        'flash_customer_name' => 'Cliente Flotante',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'notes' => 'Pago realizado en el momento',
    ], $overrides);
}

it('registers a pending flash collection without an invoice', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterFlashCollection::class)->handle($scenario['seller'], flashPayload());

    expect($event->status)->toBe(CollectionStatus::Pending)
        ->and($event->invoice_id)->toBeNull()
        ->and($event->isFlash())->toBeTrue()
        ->and($event->tenant_id)->toBe($scenario['tenant']->id)
        ->and($event->seller_user_id)->toBe($scenario['seller']->id)
        ->and((float) $event->amount)->toBe(150.5)
        ->and($event->currency)->toBe($scenario['tenant']->local_currency)
        ->and($event->flash_invoice_number)->toBe('FAC-1001')
        ->and($event->flash_cardcode)->toBe('CLI-001')
        ->and($event->flash_customer_name)->toBe('Cliente Flotante')
        ->and(Invoice::query()->count())->toBe(0);

    $audit = AuditEvent::query()->where('action', 'collection.created')->firstOrFail();

    expect($audit->details['flash'])->toBeTrue()
        ->and($audit->details['flash_invoice_number'])->toBe('FAC-1001')
        ->and($audit->details['currency'])->toBe($scenario['tenant']->local_currency);
});

it('ignores a currency supplied by the user and uses the tenant currency', function () {
    $scenario = flashScenario(['local_currency' => 'USD']);
    $this->actingAs($scenario['seller']);

    $event = app(RegisterFlashCollection::class)->handle(
        $scenario['seller'],
        flashPayload(['currency' => 'BOB']),
    );

    expect($event->currency)->toBe('USD');
});

it('rejects flash collections from roles without the flash permission', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(RegisterFlashCollection::class)->handle($scenario['supervisor'], flashPayload()))
        ->toThrow(AuthorizationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('requires a voucher for non-cash flash collections and keeps it unique per tenant', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterFlashCollection::class)->handle(
        $scenario['seller'],
        flashPayload(['payment_method' => PaymentMethod::Transfer, 'voucher_code' => null]),
    ))->toThrow(ValidationException::class);

    app(RegisterFlashCollection::class)->handle(
        $scenario['seller'],
        flashPayload(['payment_method' => PaymentMethod::Transfer, 'voucher_code' => 'TRX-1']),
    );

    expect(fn () => app(RegisterFlashCollection::class)->handle(
        $scenario['seller'],
        flashPayload(['payment_method' => PaymentMethod::Transfer, 'voucher_code' => 'TRX-1']),
    ))->toThrow(ValidationException::class);
});

it('rejects flash collections dated in the future', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterFlashCollection::class)->handle(
        $scenario['seller'],
        flashPayload(['collected_on' => now()->addDay()->toDateString()]),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('accepts a flash collection with an ERP DocNum and keeps it floating', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterFlashCollection::class)->handle($scenario['seller'], flashPayload());

    $this->actingAs($scenario['supervisor']);
    $accepted = app(AcceptCollection::class)->handle($scenario['supervisor'], $event, '100341331', true);

    expect($accepted->status)->toBe(CollectionStatus::Accepted)
        ->and($accepted->erp_docnum)->toBe('100341331')
        ->and($accepted->invoice_id)->toBeNull()
        ->and($accepted->isFlash())->toBeTrue();
});

it('issues a valid QR verification code for an accepted flash collection', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterFlashCollection::class)->handle($scenario['seller'], flashPayload());

    $this->actingAs($scenario['supervisor']);
    $accepted = app(AcceptCollection::class)->handle($scenario['supervisor'], $event, '100341331', true);

    $this->actingAs($scenario['seller']);
    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $accepted);

    expect($accepted->verificationPayload()['invoice_id'])->toBeNull()
        ->and(app(VerifyCode::class)->handle($code->code))->toBe(VerificationResult::Valid);
});

it('registers a flash collection from the page', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    Livewire::test('pages::collection-flash')
        ->assertOk()
        ->assertSee($scenario['tenant']->local_currency)
        ->set('amount', '99.90')
        ->set('flashInvoiceNumber', 'FAC-2002')
        ->set('flashCardcode', 'CLI-002')
        ->set('flashCustomerName', 'Cliente Nuevo')
        ->set('paymentMethod', PaymentMethod::Cash->value)
        ->set('collectedOn', now()->toDateString())
        ->set('confirmed', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('collections.index'));

    $event = CollectionEvent::query()->where('flash_invoice_number', 'FAC-2002')->first();

    expect($event)->not->toBeNull()
        ->and($event->invoice_id)->toBeNull()
        ->and($event->flash_customer_name)->toBe('Cliente Nuevo');
});

it('validates the confirmation checkbox on the flash page', function () {
    $scenario = flashScenario();
    $this->actingAs($scenario['seller']);

    Livewire::test('pages::collection-flash')
        ->set('amount', '10')
        ->set('flashInvoiceNumber', 'FAC-3003')
        ->set('flashCardcode', 'CLI-003')
        ->set('flashCustomerName', 'Cliente Sin Confirmar')
        ->set('collectedOn', now()->toDateString())
        ->call('save')
        ->assertHasErrors('confirmed');

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('forbids roles without the permission from opening the flash page', function () {
    $scenario = flashScenario();

    $this->actingAs($scenario['supervisor']);

    $this->withoutVite()
        ->get(route('collections.flash'))
        ->assertForbidden();

    $this->actingAs($scenario['seller']);

    $this->withoutVite()
        ->get(route('collections.flash'))
        ->assertOk();
});

it('lists and searches flash collections by their declared invoice and customer', function () {
    $scenario = flashScenario();

    CollectionEvent::factory()->flash()->create([
        'tenant_id' => $scenario['tenant']->id,
        'seller_user_id' => $scenario['seller']->id,
        'flash_invoice_number' => 'FAC-7777',
        'flash_cardcode' => 'CLI-777',
        'flash_customer_name' => 'Cliente Flash',
    ]);

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collections')
        ->assertSee('FAC-7777')
        ->assertSee('Cliente Flash')
        ->set('search', 'Cliente Flash')
        ->assertSee('FAC-7777')
        ->set('search', 'CLI-777')
        ->assertSee('FAC-7777');
});
