<?php

use App\Actions\Collection\RegisterCollection;
use App\Enums\CollectionKind;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function proofScenario(): array
{
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

    return compact('tenant', 'rep', 'seller', 'customer', 'invoice');
}

function proofPayload(array $overrides = []): array
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

it('requires a voucher code for non cash payments', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['payment_method' => PaymentMethod::Transfer]),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('accepts a cash collection without a voucher code', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(),
    );

    expect($event->voucher_code)->toBeNull();
});

it('rejects a duplicated voucher code inside the same tenant', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['payment_method' => PaymentMethod::Check, 'voucher_code' => 'V-001']),
    );

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['payment_method' => PaymentMethod::Check, 'voucher_code' => 'V-001']),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(1);
});

it('allows the same voucher code in another tenant', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['payment_method' => PaymentMethod::Check, 'voucher_code' => 'V-001']),
    );

    $otherTenant = tenantContext();
    $otherRep = SalesRep::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherSeller = memberFor($otherTenant, TenantRole::Seller, salesRep: $otherRep);
    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $otherTenant->id,
        'sales_rep_id' => $otherRep->id,
    ]);
    $otherInvoice = Invoice::factory()->create([
        'tenant_id' => $otherTenant->id,
        'customer_id' => $otherCustomer->id,
        'sales_rep_id' => $otherRep->id,
        'currency' => 'BOB',
        'total' => 1000,
        'balance' => 1000,
    ]);

    app(CurrentTenant::class)->set($otherTenant->id);
    $this->actingAs($otherSeller);

    $event = app(RegisterCollection::class)->handle(
        $otherSeller,
        $otherInvoice,
        proofPayload(['payment_method' => PaymentMethod::Check, 'voucher_code' => 'V-001']),
    );

    expect($event->voucher_code)->toBe('V-001')
        ->and($event->tenant_id)->toBe($otherTenant->id);
});

it('stores the proof file on the private disk and keeps its metadata', function () {
    Storage::fake('local');

    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['proof' => UploadedFile::fake()->image('comprobante.jpg')]),
    );

    $event->refresh();

    expect($event->proof_path)->not->toBeNull()
        ->and($event->proof_disk)->toBe('local')
        ->and($event->proof_original_name)->toBe('comprobante.jpg')
        ->and($event->proof_size)->not->toBeNull();

    Storage::disk('local')->assertExists($event->proof_path);
});

it('rejects a proof file with a forbidden extension', function () {
    Storage::fake('local');

    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $file = UploadedFile::fake()->create('comprobante.exe', 10);

    expect(fn () => app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['proof' => $file]),
    ))->toThrow(ValidationException::class);

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('lets the owner download the proof of a collection', function () {
    Storage::fake('local');

    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['proof' => UploadedFile::fake()->image('comprobante.jpg')]),
    );

    $this->get(route('collections.proof', $event->id))->assertOk();
});

it('serves the proof inline so the browser can render it', function () {
    Storage::fake('local');

    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['proof' => UploadedFile::fake()->image('comprobante.jpg')]),
    );

    $response = $this->get(route('collections.proof', $event->id));

    $response->assertOk();
    $response->assertHeader('content-type', 'image/jpeg');
    $response->assertHeader('content-disposition', 'inline; filename=comprobante.jpg');
    $response->assertHeader('cache-control', 'max-age=0, must-revalidate, private');
});

it('denies the download of a proof to a user from another tenant', function () {
    Storage::fake('local');

    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['proof' => UploadedFile::fake()->image('comprobante.jpg')]),
    );

    $otherTenant = tenantContext();
    $otherRep = SalesRep::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherSeller = memberFor($otherTenant, TenantRole::Seller, salesRep: $otherRep);
    app(CurrentTenant::class)->set($otherTenant->id);
    $this->actingAs($otherSeller);

    $this->get(route('collections.proof', $event->id))->assertForbidden();
});

it('returns not found when the collection has no proof attached', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    $event = app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(),
    );

    $this->get(route('collections.proof', $event->id))->assertNotFound();
});

it('requires the confirmation before registering a collection', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    Livewire::test('pages::collection-create', ['invoice' => $scenario['invoice']->id])
        ->set('amount', '200')
        ->set('kind', CollectionKind::Partial->value)
        ->set('paymentMethod', PaymentMethod::Cash->value)
        ->set('collectedOn', now()->toDateString())
        ->call('save')
        ->assertHasErrors('confirmed');

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('registers a collection with a voucher and a proof from the form', function () {
    Storage::fake('local');

    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    Livewire::test('pages::collection-create', ['invoice' => $scenario['invoice']->id])
        ->set('amount', '200')
        ->set('kind', CollectionKind::Partial->value)
        ->set('paymentMethod', PaymentMethod::Transfer->value)
        ->set('collectedOn', now()->toDateString())
        ->set('voucherCode', 'V-777')
        ->set('proof', UploadedFile::fake()->image('comprobante.jpg'))
        ->set('confirmed', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('collections.index'));

    $event = CollectionEvent::query()->where('invoice_id', $scenario['invoice']->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->voucher_code)->toBe('V-777')
        ->and($event->proof_path)->not->toBeNull();
});

it('requires a voucher code in the form for non cash payments', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    Livewire::test('pages::collection-create', ['invoice' => $scenario['invoice']->id])
        ->set('amount', '200')
        ->set('kind', CollectionKind::Partial->value)
        ->set('paymentMethod', PaymentMethod::Transfer->value)
        ->set('collectedOn', now()->toDateString())
        ->set('confirmed', true)
        ->call('save')
        ->assertHasErrors('voucherCode');

    expect(CollectionEvent::query()->count())->toBe(0);
});

it('surfaces a duplicated voucher code as a validation error in the form', function () {
    $scenario = proofScenario();
    $this->actingAs($scenario['seller']);

    app(RegisterCollection::class)->handle(
        $scenario['seller'],
        $scenario['invoice'],
        proofPayload(['payment_method' => PaymentMethod::Check, 'voucher_code' => 'V-888']),
    );

    $secondInvoice = Invoice::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
        'sales_rep_id' => $scenario['rep']->id,
        'currency' => 'BOB',
        'total' => 1000,
        'balance' => 1000,
    ]);

    Livewire::test('pages::collection-create', ['invoice' => $secondInvoice->id])
        ->set('amount', '200')
        ->set('kind', CollectionKind::Partial->value)
        ->set('paymentMethod', PaymentMethod::Check->value)
        ->set('collectedOn', now()->toDateString())
        ->set('voucherCode', 'V-888')
        ->set('confirmed', true)
        ->call('save')
        ->assertHasErrors(['voucher_code' => 'unique']);
});
