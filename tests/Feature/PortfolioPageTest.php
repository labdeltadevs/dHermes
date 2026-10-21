<?php

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function portfolioPageScenario(): array
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
        'number' => 'INV-OWN-001',
        'balance' => 1000,
    ]);

    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $otherRep->id,
        'name' => 'Cliente Ajeno',
    ]);

    $otherInvoice = Invoice::factory()->create([
        'customer_id' => $otherCustomer->id,
        'sales_rep_id' => $otherRep->id,
        'number' => 'INV-OTHER-001',
        'balance' => 2000,
    ]);

    $closedInvoice = Invoice::factory()->closed()->create([
        'customer_id' => $ownCustomer->id,
        'sales_rep_id' => $salesRep->id,
        'number' => 'INV-CLOSED-001',
    ]);

    $cancelledInvoice = Invoice::factory()->cancelled()->create([
        'customer_id' => $ownCustomer->id,
        'sales_rep_id' => $salesRep->id,
        'number' => 'INV-CANCELLED-001',
    ]);

    $otherTenant = Tenant::factory()->create(['name' => 'Empresa Ajena']);
    app(CurrentTenant::class)->set($otherTenant->id);

    $foreignInvoice = Invoice::factory()->create([
        'number' => 'INV-FOREIGN-001',
    ]);

    app(CurrentTenant::class)->set($tenant->id);

    return compact(
        'tenant',
        'salesRep',
        'admin',
        'ownInvoice',
        'otherInvoice',
        'closedInvoice',
        'cancelledInvoice',
        'foreignInvoice',
    );
}

it('shows the tenant portfolio to an admin', function () {
    $scenario = portfolioPageScenario();

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::portfolio')
        ->assertOk()
        ->assertSee('INV-OWN-001')
        ->assertSee('INV-OTHER-001')
        ->assertDontSee('INV-CLOSED-001')
        ->assertDontSee('INV-CANCELLED-001')
        ->assertDontSee('INV-FOREIGN-001');
});

it('shows only the assigned portfolio to a seller', function () {
    $scenario = portfolioPageScenario();
    $seller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $scenario['salesRep']);

    $this->actingAs($seller);

    Livewire::test('pages::portfolio')
        ->assertOk()
        ->assertSee('INV-OWN-001')
        ->assertDontSee('INV-OTHER-001')
        ->assertDontSee('INV-CLOSED-001');
});

it('filters the portfolio by search and due status', function () {
    $scenario = portfolioPageScenario();
    $seller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $scenario['salesRep']);

    Invoice::factory()->create([
        'customer_id' => $scenario['ownInvoice']->customer_id,
        'sales_rep_id' => $scenario['salesRep']->id,
        'number' => 'INV-OVERDUE-001',
        'due_date' => now()->subDays(5)->toDateString(),
        'balance' => 500,
    ]);

    $this->actingAs($seller);

    Livewire::test('pages::portfolio')
        ->set('search', 'INV-OVERDUE-001')
        ->assertSee('INV-OVERDUE-001')
        ->assertDontSee('INV-OWN-001')
        ->set('search', '')
        ->set('due', 'overdue')
        ->assertSee('INV-OVERDUE-001')
        ->assertDontSee('INV-OWN-001');
});

it('blocks portfolio access without an active membership', function () {
    $tenant = tenantContext();
    $user = memberFor($tenant, TenantRole::Admin);

    TenantUser::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->update(['active' => false]);

    $this->actingAs($user)
        ->get(route('portfolio'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('shows the grouped portfolio link and sales rep SLP labels to approvers', function () {
    $scenario = portfolioPageScenario();

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::portfolio')
        ->assertSee('Agrupado por vendedor')
        ->assertSeeHtml(route('portfolio.by-rep'))
        ->assertSee($scenario['salesRep']->name.' · SLP '.$scenario['salesRep']->slpcode);
});

it('shows a collect action to a seller on their own invoices only', function () {
    $scenario = portfolioPageScenario();
    $seller = memberFor($scenario['tenant'], TenantRole::Seller, salesRep: $scenario['salesRep']);

    $this->actingAs($seller);

    Livewire::test('pages::portfolio')
        ->assertSee('Cobrar')
        ->assertSeeHtml(route('collections.create', ['invoice' => $scenario['ownInvoice']->id]))
        ->assertDontSeeHtml(route('collections.create', ['invoice' => $scenario['otherInvoice']->id]));
});

it('does not show a collect action to approvers', function () {
    $scenario = portfolioPageScenario();

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::portfolio')
        ->assertDontSee('Cobrar');
});

it('paginates the portfolio with numbered page links', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $salesRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $salesRep->id,
    ]);

    Invoice::factory()->count(30)->create([
        'customer_id' => $customer->id,
        'sales_rep_id' => $salesRep->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::portfolio')
        ->assertSee('Mostrando 1 a 25 de 30 resultados')
        ->assertSeeHtml('aria-label="Ir a la página 2"')
        ->call('gotoPage', 2)
        ->assertSee('Mostrando 26 a 30 de 30 resultados')
        ->assertSeeHtml('aria-current="page"');
});
