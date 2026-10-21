<?php

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Queries\SellerPortfolioQuery;
use App\Support\Tenancy\CurrentTenant;
use Symfony\Component\HttpKernel\Exception\HttpException;

function portfolioScenario(): array
{
    $tenant = tenantContext();
    $firstRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $secondRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);

    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $firstRep);
    $collector = memberFor($tenant, TenantRole::Collector);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    $admin = memberFor($tenant, TenantRole::Admin);

    $ownCustomer = Customer::factory()->create(['sales_rep_id' => $firstRep->id]);
    $otherCustomer = Customer::factory()->create(['sales_rep_id' => $secondRep->id]);
    $collectedCustomer = Customer::factory()->create([
        'sales_rep_id' => $secondRep->id,
        'collector_user_id' => $collector->id,
    ]);

    $ownInvoice = Invoice::factory()->create([
        'customer_id' => $ownCustomer->id,
        'sales_rep_id' => $firstRep->id,
        'total' => 1000,
        'balance' => 1000,
    ]);
    $otherInvoice = Invoice::factory()->create([
        'customer_id' => $otherCustomer->id,
        'sales_rep_id' => $secondRep->id,
    ]);
    $collectedInvoice = Invoice::factory()->create([
        'customer_id' => $collectedCustomer->id,
        'sales_rep_id' => $secondRep->id,
    ]);

    return compact(
        'tenant',
        'firstRep',
        'secondRep',
        'seller',
        'collector',
        'supervisor',
        'admin',
        'ownCustomer',
        'otherCustomer',
        'ownInvoice',
        'otherInvoice',
        'collectedInvoice',
    );
}

it('returns only the seller portfolio', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $ids = app(SellerPortfolioQuery::class)->for($scenario['seller'])->pluck('id')->all();

    expect($ids)->toBe([$scenario['ownInvoice']->id]);
});

it('scopes the seller portfolio by the invoice sales rep', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $ownInvoiceForOtherCustomer = Invoice::factory()->create([
        'customer_id' => $scenario['otherCustomer']->id,
        'sales_rep_id' => $scenario['firstRep']->id,
    ]);
    $foreignInvoiceForOwnCustomer = Invoice::factory()->create([
        'customer_id' => $scenario['ownCustomer']->id,
        'sales_rep_id' => $scenario['secondRep']->id,
    ]);

    $ids = app(SellerPortfolioQuery::class)->for($scenario['seller'])->pluck('id')->all();

    expect($ids)
        ->toContain($scenario['ownInvoice']->id, $ownInvoiceForOtherCustomer->id)
        ->not->toContain($scenario['otherInvoice']->id, $foreignInvoiceForOwnCustomer->id);
});

it('returns assigned customers for collectors', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $ids = app(SellerPortfolioQuery::class)->for($scenario['collector'])->pluck('id')->all();

    expect($ids)->toBe([$scenario['collectedInvoice']->id]);
});

it('returns the full tenant portfolio for supervisors and admins', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $expected = [
        $scenario['ownInvoice']->id,
        $scenario['otherInvoice']->id,
        $scenario['collectedInvoice']->id,
    ];

    expect(app(SellerPortfolioQuery::class)->for($scenario['supervisor'])->pluck('id')->all())
        ->toEqualCanonicalizing($expected)
        ->and(app(SellerPortfolioQuery::class)->for($scenario['admin'])->pluck('id')->all())
        ->toEqualCanonicalizing($expected);
});

it('excludes settled and cancelled invoices', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $settled = Invoice::factory()->closed()->create([
        'customer_id' => Customer::factory()->create(['sales_rep_id' => $scenario['seller']->currentMembership()->sales_rep_id])->id,
        'sales_rep_id' => $scenario['seller']->currentMembership()->sales_rep_id,
    ]);
    $cancelled = Invoice::factory()->cancelled()->create([
        'customer_id' => Customer::factory()->create(['sales_rep_id' => $scenario['seller']->currentMembership()->sales_rep_id])->id,
        'sales_rep_id' => $scenario['seller']->currentMembership()->sales_rep_id,
    ]);

    $ids = app(SellerPortfolioQuery::class)->for($scenario['seller'])->pluck('id')->all();

    expect($ids)->toBe([$scenario['ownInvoice']->id])
        ->and($ids)->not->toContain($settled->id)
        ->and($ids)->not->toContain($cancelled->id);
});

it('returns portfolio totals grouped by currency without mixing currencies', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $repId = $scenario['seller']->currentMembership()->sales_rep_id;

    Invoice::factory()->create([
        'customer_id' => Customer::factory()->create(['sales_rep_id' => $repId])->id,
        'sales_rep_id' => $repId,
        'currency' => 'USD',
        'total' => 500,
        'balance' => 500,
    ]);

    $totals = app(SellerPortfolioQuery::class)->totalsByCurrency($scenario['seller']);

    expect($totals)->toHaveKeys(['BOB', 'USD'])
        ->and((float) $totals['BOB'])->toBe(1000.0)
        ->and((float) $totals['USD'])->toBe(500.0);
});

it('blocks sellers without a sales rep assignment', function () {
    $tenant = tenantContext();
    $seller = memberFor($tenant, TenantRole::Seller);

    expect(fn () => app(SellerPortfolioQuery::class)->for($seller))
        ->toThrow(HttpException::class);
});

it('filters the portfolio by search and due date', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $repId = $scenario['seller']->currentMembership()->sales_rep_id;

    $overdue = Invoice::factory()->create([
        'customer_id' => $scenario['ownInvoice']->customer_id,
        'sales_rep_id' => $repId,
        'number' => 'INV-OVERDUE-001',
        'due_date' => now()->subDays(3)->toDateString(),
        'balance' => 300,
    ]);

    $ids = app(SellerPortfolioQuery::class)
        ->filtered($scenario['seller'], [
            'search' => 'INV-OVERDUE-001',
            'due' => 'overdue',
            'today' => now()->toDateString(),
        ])
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$overdue->id]);
});

it('summarizes the portfolio by currency and overdue balance', function () {
    $scenario = portfolioScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $repId = $scenario['seller']->currentMembership()->sales_rep_id;

    Invoice::factory()->create([
        'customer_id' => Customer::factory()->create(['sales_rep_id' => $repId])->id,
        'sales_rep_id' => $repId,
        'currency' => 'USD',
        'total' => 500,
        'balance' => 500,
        'due_date' => now()->subDays(10)->toDateString(),
    ]);

    $summary = app(SellerPortfolioQuery::class)
        ->summaryByCurrency($scenario['seller'], ['today' => now()->toDateString()])
        ->keyBy('currency');

    expect($summary)->toHaveKeys(['BOB', 'USD'])
        ->and((float) $summary['BOB']->balance)->toBe(1000.0)
        ->and((int) $summary['BOB']->overdue_count)->toBe(0)
        ->and((float) $summary['USD']->balance)->toBe(500.0)
        ->and((int) $summary['USD']->overdue_count)->toBe(1);
});
