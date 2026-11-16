<?php

use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Models\SalesRep;
use App\Models\User;
use App\Queries\CollectionQuery;
use App\Support\Collection\CollectionReportPdfGenerator;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Route;
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

/**
 * @return array{from: string, to: string, status: string, payment_method: string, seller_user_id: string, currency: string}
 */
function collectionReportFilters(array $overrides = []): array
{
    return array_merge([
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
        'status' => CollectionStatus::Accepted->value,
        'payment_method' => '',
        'seller_user_id' => '',
        'currency' => '',
    ], $overrides);
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
        ->assertSee('Total general de pagos recibidos')
        ->assertSee('750,00')
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

it('does not expose another tenant collections in the PDF', function () {
    collectionReportScenario();

    $otherTenant = tenantContext(['code' => 'OTRO']);
    $otherRep = SalesRep::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherTenantSeller = memberFor($otherTenant, TenantRole::Seller, salesRep: $otherRep);
    app(CurrentTenant::class)->set($otherTenant->id);

    $this->actingAs($otherTenantSeller);

    $response = $this->get(route('collections.report.pdf'));

    expect($response)->assertOk();
    expect($response->getContent())->not->toContain('FAC-9001');
});

it('offers only the PDF download and no CSV export', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    expect(Route::has('collections.report.csv'))->toBeFalse();

    Livewire::test('pages::collection-reports')
        ->assertOk()
        ->assertSee('Descargar PDF')
        ->assertDontSee('Exportar CSV');
});

it('renders the PDF report with a single payment method column, arqueo and user identification', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    $filters = collectionReportFilters();
    $events = app(CollectionQuery::class)->filtered($scenario['supervisor'], $filters)->get();

    $html = app(CollectionReportPdfGenerator::class)->generateHtml(
        $events,
        $filters,
        'Empresa Reporte',
        null,
        CollectionStatus::Accepted->label(),
        null,
        (string) $scenario['supervisor']->name,
    );

    expect($html)
        ->toContain('Forma de pago')
        ->toContain('Arqueo de cobranzas por método de pago')
        ->toContain('Total general de pagos recibidos')
        ->toContain('Generado por:</strong> '.$scenario['supervisor']->name)
        ->toContain('N° reporte:')
        ->toContain('RP-000000001')
        ->toContain('FLASH-777')
        ->toContain('Depósito-Transferencia')
        ->toContain('500,00')
        ->toContain('750,00')
        ->not->toContain('Identificador interno')
        ->not->toContain('Subtotal')
        ->not->toContain('RP-000000003');
});

it('lists every invoice of a single payment separated by commas', function () {
    $scenario = collectionReportScenario();

    $receipt = Receipt::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'customer_id' => $scenario['customer']->id,
        'number' => '900100',
        'currency' => 'BOB',
        'total' => 600,
    ]);

    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['invoice']->id,
        'invtype' => '13',
    ]);

    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $scenario['otherInvoice']->id,
        'invtype' => '13',
    ]);

    CollectionEvent::factory()->accepted()->create([
        'tenant_id' => $scenario['tenant']->id,
        'invoice_id' => $scenario['invoice']->id,
        'seller_user_id' => $scenario['seller']->id,
        'amount' => 600,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Check,
        'collected_on' => now()->toDateString(),
        'number' => 6,
        'number_code' => 'RP-000000006',
        'erp_docnum' => '900100',
    ]);

    CollectionEvent::factory()->accepted()->flash()->create([
        'tenant_id' => $scenario['tenant']->id,
        'seller_user_id' => $scenario['seller']->id,
        'amount' => 75,
        'currency' => 'BOB',
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'number' => 7,
        'number_code' => 'RP-000000007',
        'flash_invoice_number' => 'FAC-A , FAC-B,FAC-C',
        'flash_cardcode' => 'C-MULTI',
        'flash_customer_name' => 'Cliente Multi',
    ]);

    $this->actingAs($scenario['supervisor']);

    $filters = collectionReportFilters();
    $events = app(CollectionQuery::class)->filtered($scenario['supervisor'], $filters)->get();

    $html = app(CollectionReportPdfGenerator::class)->generateHtml(
        $events,
        $filters,
        'Empresa Reporte',
        null,
        CollectionStatus::Accepted->label(),
        null,
        (string) $scenario['supervisor']->name,
    );

    expect($html)
        ->toContain('FAC-9001, FAC-9002')
        ->toContain('FAC-A, FAC-B, FAC-C');
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

    $this->get(route('collections.report.pdf'))->assertRedirect();
});

it('downloads the report as a PDF document', function () {
    $scenario = collectionReportScenario();

    $this->actingAs($scenario['supervisor']);

    $response = $this->get(route('collections.report.pdf', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader(
        'content-disposition',
        'attachment; filename="cobranzas_'.now()->toDateString().'_a_'.now()->toDateString().'.pdf"',
    );

    expect($response->getContent())->toStartWith('%PDF');
});
