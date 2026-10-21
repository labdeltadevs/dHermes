<?php

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Queries\SellerPortfolioQuery;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function portfolioByRepScenario(): array
{
    $tenant = tenantContext();
    $repA = SalesRep::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Ana Vendedora',
        'slpcode' => '101',
    ]);
    $repB = SalesRep::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Beto Vendedor',
        'slpcode' => '202',
    ]);

    $admin = memberFor($tenant, TenantRole::Admin);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $repA);

    $customerA = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $repA->id,
        'name' => 'Cliente de Ana',
    ]);
    $customerB = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $repB->id,
        'name' => 'Cliente de Beto',
    ]);

    $currentInvoice = Invoice::factory()->create([
        'customer_id' => $customerA->id,
        'sales_rep_id' => $repA->id,
        'currency' => 'BOB',
        'total' => 1000,
        'balance' => 1000,
        'due_date' => now()->addDays(10)->toDateString(),
    ]);

    $overdueInvoice = Invoice::factory()->create([
        'customer_id' => $customerA->id,
        'sales_rep_id' => $repA->id,
        'currency' => 'BOB',
        'total' => 500,
        'balance' => 500,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $overdueUsdInvoice = Invoice::factory()->create([
        'customer_id' => $customerA->id,
        'sales_rep_id' => $repA->id,
        'currency' => 'USD',
        'total' => 300,
        'balance' => 300,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);

    $otherInvoice = Invoice::factory()->create([
        'customer_id' => $customerB->id,
        'sales_rep_id' => $repB->id,
        'currency' => 'BOB',
        'total' => 2000,
        'balance' => 2000,
        'due_date' => now()->addDays(15)->toDateString(),
    ]);

    Invoice::factory()->closed()->create([
        'customer_id' => $customerA->id,
        'sales_rep_id' => $repA->id,
        'currency' => 'BOB',
        'total' => 900,
    ]);

    Invoice::factory()->cancelled()->create([
        'customer_id' => $customerA->id,
        'sales_rep_id' => $repA->id,
        'currency' => 'BOB',
        'total' => 800,
        'balance' => 800,
    ]);

    return compact(
        'tenant',
        'repA',
        'repB',
        'admin',
        'supervisor',
        'seller',
        'customerA',
        'customerB',
        'currentInvoice',
        'overdueInvoice',
        'overdueUsdInvoice',
        'otherInvoice',
    );
}

it('summarizes the portfolio by sales rep and currency', function () {
    $scenario = portfolioByRepScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $rows = app(SellerPortfolioQuery::class)->summaryBySalesRep($scenario['admin'], [
        'today' => now()->toDateString(),
    ]);

    $anaBob = $rows->first(fn ($row): bool => $row->sales_rep_id === $scenario['repA']->id && $row->currency === 'BOB');
    $anaUsd = $rows->first(fn ($row): bool => $row->sales_rep_id === $scenario['repA']->id && $row->currency === 'USD');
    $betoBob = $rows->first(fn ($row): bool => $row->sales_rep_id === $scenario['repB']->id && $row->currency === 'BOB');

    expect($anaBob)->not->toBeNull()
        ->and($anaBob->sales_rep_name)->toBe('Ana Vendedora')
        ->and($anaBob->slpcode)->toBe('101')
        ->and((int) $anaBob->invoice_count)->toBe(2)
        ->and((float) $anaBob->balance)->toBe(1500.0)
        ->and((int) $anaBob->overdue_count)->toBe(1)
        ->and((float) $anaBob->overdue_balance)->toBe(500.0)
        ->and((int) $anaBob->customer_count)->toBe(1);

    expect($anaUsd)->not->toBeNull()
        ->and((int) $anaUsd->invoice_count)->toBe(1)
        ->and((float) $anaUsd->balance)->toBe(300.0)
        ->and((int) $anaUsd->overdue_count)->toBe(1)
        ->and((float) $anaUsd->overdue_balance)->toBe(300.0);

    expect($betoBob)->not->toBeNull()
        ->and((float) $betoBob->balance)->toBe(2000.0)
        ->and((int) $betoBob->overdue_count)->toBe(0)
        ->and((float) $betoBob->overdue_balance)->toBe(0.0);

    expect($rows->first()->sales_rep_id)->toBe($scenario['repB']->id);
});

it('filters the grouped summary by sales rep search, currency and due status', function () {
    $scenario = portfolioByRepScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $bySearch = app(SellerPortfolioQuery::class)->summaryBySalesRep($scenario['admin'], [
        'search' => 'Ana',
        'today' => now()->toDateString(),
    ]);

    expect($bySearch)->not->toBeEmpty()
        ->and($bySearch->every(fn ($row): bool => $row->sales_rep_id === $scenario['repA']->id))->toBeTrue();

    $byUsd = app(SellerPortfolioQuery::class)->summaryBySalesRep($scenario['admin'], [
        'currency' => 'USD',
        'today' => now()->toDateString(),
    ]);

    expect($byUsd)->toHaveCount(1)
        ->and($byUsd->first()->currency)->toBe('USD')
        ->and($byUsd->first()->sales_rep_id)->toBe($scenario['repA']->id);

    $overdue = app(SellerPortfolioQuery::class)->summaryBySalesRep($scenario['admin'], [
        'due' => 'overdue',
        'today' => now()->toDateString(),
    ]);

    expect($overdue->sum('invoice_count'))->toBe(2)
        ->and((float) $overdue->sum('balance'))->toBe(800.0)
        ->and($overdue->every(fn ($row): bool => (int) $row->overdue_count === (int) $row->invoice_count))->toBeTrue();
});

it('shows the grouped portfolio page to approvers', function () {
    $scenario = portfolioByRepScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::portfolio-by-rep')
        ->assertOk()
        ->assertSee('Cartera por vendedor')
        ->assertSee('Ana Vendedora')
        ->assertSee('Beto Vendedor')
        ->assertSee('SLP 101')
        ->assertSee('SLP 202')
        ->assertSee('2.000,00')
        ->assertSee('Volver a Cartera');
});

it('lets supervisors view the grouped portfolio page', function () {
    $scenario = portfolioByRepScenario();

    $this->actingAs($scenario['supervisor']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::portfolio-by-rep')
        ->assertOk()
        ->assertSee('Ana Vendedora');
});

it('filters the grouped portfolio page by sales rep', function () {
    $scenario = portfolioByRepScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::portfolio-by-rep')
        ->set('search', 'Ana')
        ->assertSee('Ana Vendedora')
        ->assertDontSee('Beto Vendedor')
        ->set('search', '')
        ->set('currency', 'USD')
        ->assertSee('USD')
        ->assertDontSee('Beto Vendedor');
});

it('forbids sellers from viewing the grouped portfolio page', function () {
    $scenario = portfolioByRepScenario();

    $this->actingAs($scenario['seller'])
        ->get(route('portfolio.by-rep'))
        ->assertForbidden();
});
