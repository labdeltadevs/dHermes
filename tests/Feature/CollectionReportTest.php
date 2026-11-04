<?php

use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function collectionReportScenario(): array
{
    $tenant = tenantContext(['code' => 'RP']);

    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    $otherSeller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $rep->id,
        'name' => 'Cliente Reporte',
        'cardcode' => 'C-REP-01',
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'number' => 'FAC-9001',
        'currency' => 'BOB',
        'total' => 5000,
        'balance' => 5000,
    ]);

    $otherInvoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
        'number' => 'FAC-9002',
        'currency' => 'BOB',
        'total' => 5000,
        'balance' => 5000,
    ]);

    $cash = CollectionEvent::factory()->accepted()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'seller_user_id' => $seller->id,
        'amount' => 100,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'number' => 1,
        'number_code' => 'RP-000000001',
    ]);

    $qr = CollectionEvent::factory()->accepted()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'seller_user_id' => $seller->id,
        'amount' => 200,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Qr,
        'collected_on' => now()->toDateString(),
        'number' => 2,
        'number_code' => 'RP-000000002',
    ]);

    $pending = CollectionEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'seller_user_id' => $seller->id,
        'amount' => 300,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'status' => CollectionStatus::Pending,
        'number' => 3,
        'number_code' => 'RP-000000003',
    ]);

    $flash = CollectionEvent::factory()->accepted()->flash()->create([
        'tenant_id' => $tenant->id,
        'seller_user_id' => $seller->id,
        'amount' => 50,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::DepositTransfer,
        'collected_on' => now()->toDateString(),
        'number' => 4,
        'number_code' => 'RP-000000004',
        'flash_invoice_number' => 'FLASH-777',
        'flash_cardcode' => 'C-FLASH',
        'flash_customer_name' => 'Cliente Flash',
    ]);

    $foreign = CollectionEvent::factory()->accepted()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $otherInvoice->id,
        'seller_user_id' => $otherSeller->id,
        'amount' => 400,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'number' => 5,
        'number_code' => 'RP-000000005',
    ]);

    return compact(
        'tenant',
        'seller',
        'supervisor',
        'otherSeller',
        'customer',
        'invoice',
        'otherInvoice',
        'cash',
        'qr',
        'pending',
        'flash',
        'foreign',
    );
}

it('reports only accepted collections by default grouped by payment method', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collection-reports')
        ->assertOk()
        ->assertSee('Efectivo')
        ->assertSee('QR')
        ->assertSee('Depósito-Transferencia')
        ->assertSee('FAC-9001')
        ->assertSee('FLASH-777')
        ->assertSee('100,00')
        ->assertSee('200,00')
        ->assertSee('50,00')
        ->assertDontSee('RP-000000003');
});

it('filters the report by status and payment method', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collection-reports')
        ->set('status', '')
        ->assertSee('RP-000000003')
        ->set('status', CollectionStatus::Accepted->value)
        ->set('paymentMethod', PaymentMethod::Qr->value)
        ->assertSee('FAC-9001')
        ->assertSee('RP-000000002')
        ->assertDontSee('RP-000000001')
        ->assertDontSee('FLASH-777');
});

it('shows a seller only their own collections in the report', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['seller']);

    Livewire::test('pages::collection-reports')
        ->assertOk()
        ->assertSee('FAC-9001')
        ->assertDontSee('FAC-9002');
});

it('does not expose another tenant collections', function () {
    collectionReportScenario();

    $otherTenant = tenantContext(['code' => 'OTRO']);
    $otherRep = SalesRep::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherTenantSeller = memberFor($otherTenant, TenantRole::Seller, salesRep: $otherRep);
    app(CurrentTenant::class)->set($otherTenant->id);

    $this->actingAs($otherTenantSeller);

    Livewire::test('pages::collection-reports')
        ->assertOk()
        ->assertDontSee('FAC-9001');

    $response = $this->get(route('collections.report.csv'));
    $content = $response->streamedContent();

    expect($content)->not->toContain('FAC-9001');
});

it('exports the report as CSV with separated columns and method subtotals', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    $response = $this->get(route('collections.report.csv', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertHeader('content-disposition', 'attachment; filename=cobranzas_'.now()->toDateString().'_a_'.now()->toDateString().'.csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Identificador app')
        ->toContain('Identificador interno (UUID)')
        ->toContain('Factura (NumAtCard)')
        ->toContain('Monto asignado')
        ->toContain('Código cliente')
        ->toContain('Cliente')
        ->toContain('Estado')
        ->toContain('MÉTODO: EFECTIVO')
        ->toContain('MÉTODO: QR')
        ->toContain('MÉTODO: DEPÓSITO-TRANSFERENCIA')
        ->toContain('RP-000000001')
        ->toContain('FAC-9001')
        ->toContain('C-REP-01')
        ->toContain('Cliente Reporte')
        ->toContain('Subtotal Efectivo (BOB)')
        ->toContain('Subtotal QR (BOB)')
        ->toContain('100.00')
        ->toContain('200.00')
        ->not->toContain('RP-000000003');
});

it('respects the payment method filter in the CSV export', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    $response = $this->get(route('collections.report.csv', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
        'payment_method' => PaymentMethod::Qr->value,
    ]));

    $content = $response->streamedContent();

    expect($content)
        ->toContain('RP-000000002')
        ->not->toContain('RP-000000001')
        ->not->toContain('RP-000000004');
});

it('allows an approver to filter the report by seller', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::collection-reports')
        ->assertSee('FAC-9002')
        ->set('sellerId', $scenario['seller']->id)
        ->assertSee('FAC-9001')
        ->assertDontSee('FAC-9002');
});

it('redirects a user without an active membership away from the report', function () {
    collectionReportScenario();

    $this->actingAs(User::factory()->create());

    $this->get(route('collections.report.csv'))->assertRedirect();
});
