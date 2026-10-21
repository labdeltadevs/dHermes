<?php

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function receiptPageScenario(): array
{
    $tenant = tenantContext(['name' => 'Empresa Principal']);
    $salesRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $otherRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $admin = memberFor($tenant, TenantRole::Admin);

    $ownCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $salesRep->id,
        'name' => 'Cliente Propio',
    ]);

    $ownInvoice = Invoice::factory()->create([
        'customer_id' => $ownCustomer->id,
        'sales_rep_id' => $salesRep->id,
    ]);
    $ownReceipt = Receipt::factory()->create([
        'customer_id' => $ownCustomer->id,
        'number' => 'RCT-OWN-001',
        'total' => 111.11,
        'receipt_date' => now()->subDays(2)->toDateString(),
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $tenant->id,
        'receipt_id' => $ownReceipt->id,
        'invoice_id' => $ownInvoice->id,
        'applied_amount' => 111.11,
    ]);

    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $otherRep->id,
        'name' => 'Cliente Ajeno',
    ]);
    $otherInvoice = Invoice::factory()->create([
        'customer_id' => $otherCustomer->id,
        'sales_rep_id' => $otherRep->id,
    ]);
    $otherReceipt = Receipt::factory()->create([
        'customer_id' => $otherCustomer->id,
        'number' => 'RCT-OTHER-001',
        'total' => 222.22,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $tenant->id,
        'receipt_id' => $otherReceipt->id,
        'invoice_id' => $otherInvoice->id,
        'applied_amount' => 222.22,
    ]);

    $mixedReceipt = Receipt::factory()->create([
        'customer_id' => $otherCustomer->id,
        'number' => 'RCT-MIXED-001',
        'total' => 1111.10,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $tenant->id,
        'receipt_id' => $mixedReceipt->id,
        'invoice_id' => $otherInvoice->id,
        'applied_amount' => 777.77,
    ]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $tenant->id,
        'receipt_id' => $mixedReceipt->id,
        'invoice_id' => $ownInvoice->id,
        'applied_amount' => 333.33,
    ]);

    $cancelledReceipt = Receipt::factory()->create([
        'customer_id' => $ownCustomer->id,
        'number' => 'RCT-CANCELLED-001',
        'cancelled' => true,
    ]);

    $otherTenant = Tenant::factory()->create(['name' => 'Empresa Ajena']);
    app(CurrentTenant::class)->set($otherTenant->id);

    $foreignReceipt = Receipt::factory()->create(['number' => 'RCT-FOREIGN-001']);
    $foreignInvoice = Invoice::factory()->create();
    ReceiptApplication::factory()->create([
        'receipt_id' => $foreignReceipt->id,
        'invoice_id' => $foreignInvoice->id,
    ]);

    app(CurrentTenant::class)->set($tenant->id);

    return compact(
        'tenant',
        'salesRep',
        'admin',
        'ownReceipt',
        'otherReceipt',
        'mixedReceipt',
        'cancelledReceipt',
        'foreignReceipt',
    );
}

it('shows the tenant receipts to an admin', function () {
    $scenario = receiptPageScenario();

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::receipts')
        ->assertOk()
        ->assertSee('RCT-OWN-001')
        ->assertSee('RCT-OTHER-001')
        ->assertSee('RCT-MIXED-001')
        ->assertSee('1.111,10')
        ->assertSee('BOB 1.444,43')
        ->assertDontSee('RCT-CANCELLED-001')
        ->assertDontSee('RCT-FOREIGN-001');
});

it('shows receipts that apply to the seller invoices', function () {
    $scenario = receiptPageScenario();
    $seller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $scenario['salesRep']);

    $this->actingAs($seller);

    Livewire::test('pages::receipts')
        ->assertOk()
        ->assertSee('RCT-OWN-001')
        ->assertSee('RCT-MIXED-001')
        ->assertDontSee('RCT-OTHER-001')
        ->assertDontSee('RCT-CANCELLED-001');
});

it('shows only the amount applied to the seller invoices', function () {
    $scenario = receiptPageScenario();
    $seller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $scenario['salesRep']);

    $this->actingAs($seller);

    Livewire::test('pages::receipts')
        ->assertOk()
        ->assertSee('Aplicado a mi cartera')
        ->assertSee('BOB 444,44')
        ->assertSee('333,33')
        ->assertDontSee('Monto cobrado')
        ->assertDontSee('1.111,10')
        ->assertDontSee('777,77');
});

it('filters receipts by search and date range', function () {
    $scenario = receiptPageScenario();
    $seller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $scenario['salesRep']);

    $this->actingAs($seller);

    Livewire::test('pages::receipts')
        ->set('search', 'RCT-OWN-001')
        ->assertSee('RCT-OWN-001')
        ->assertDontSee('RCT-OTHER-001')
        ->set('search', '')
        ->set('to', now()->subDays(1)->toDateString())
        ->assertSee('RCT-OWN-001');
});

it('shows the sales rep SLP in the selector and seller column', function () {
    $scenario = receiptPageScenario();

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::receipts')
        ->assertSee($scenario['salesRep']->name.' · SLP '.$scenario['salesRep']->slpcode)
        ->assertSee('SLP '.$scenario['salesRep']->slpcode);
});

it('paginates receipts with numbered page links', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Receipt::factory()->count(30)->create(['customer_id' => $customer->id]);

    $this->actingAs($admin);

    Livewire::test('pages::receipts')
        ->assertSee('Mostrando 1 a 25 de 30 resultados')
        ->assertSeeHtml('aria-label="Ir a la página 2"')
        ->call('gotoPage', 2)
        ->assertSee('Mostrando 26 a 30 de 30 resultados')
        ->assertSeeHtml('aria-current="page"');
});
