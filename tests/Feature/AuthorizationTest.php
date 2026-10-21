<?php

use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\CashSession;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Gate;

function policyScenario(): array
{
    $tenant = tenantContext();
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $otherRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);

    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $otherSeller = memberFor($tenant, TenantRole::Seller, salesRep: $otherRep);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);

    $customer = Customer::factory()->create(['sales_rep_id' => $rep->id]);
    $otherCustomer = Customer::factory()->create(['sales_rep_id' => $otherRep->id]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
    ]);
    $otherInvoice = Invoice::factory()->create([
        'customer_id' => $otherCustomer->id,
        'sales_rep_id' => $otherRep->id,
    ]);

    return compact(
        'tenant',
        'rep',
        'otherRep',
        'seller',
        'otherSeller',
        'supervisor',
        'customer',
        'otherCustomer',
        'invoice',
        'otherInvoice',
    );
}

it('allows a seller to see only their own portfolio', function () {
    $scenario = policyScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    expect(Gate::forUser($scenario['seller'])->allows('view', $scenario['invoice']))->toBeTrue()
        ->and(Gate::forUser($scenario['seller'])->allows('view', $scenario['otherInvoice']))->toBeFalse()
        ->and(Gate::forUser($scenario['supervisor'])->allows('view', $scenario['otherInvoice']))->toBeTrue();
});

it('bases seller invoice access on the invoice sales rep', function () {
    $scenario = policyScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $invoiceForOtherCustomer = Invoice::factory()->create([
        'customer_id' => $scenario['otherCustomer']->id,
        'sales_rep_id' => $scenario['rep']->id,
    ]);
    $invoiceForOwnCustomer = Invoice::factory()->create([
        'customer_id' => $scenario['customer']->id,
        'sales_rep_id' => $scenario['otherRep']->id,
    ]);

    expect(Gate::forUser($scenario['seller'])->allows('view', $invoiceForOtherCustomer))->toBeTrue()
        ->and(Gate::forUser($scenario['seller'])->allows('view', $invoiceForOwnCustomer))->toBeFalse();
});

it('bases seller receipt access on the applied invoices', function () {
    $scenario = policyScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $receiptForSeller = Receipt::factory()->create(['customer_id' => $scenario['otherCustomer']->id]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receiptForSeller->id,
        'invoice_id' => $scenario['invoice']->id,
        'applied_amount' => 100,
    ]);

    $receiptForOtherSeller = Receipt::factory()->create(['customer_id' => $scenario['customer']->id]);
    ReceiptApplication::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'receipt_id' => $receiptForOtherSeller->id,
        'invoice_id' => $scenario['otherInvoice']->id,
        'applied_amount' => 100,
    ]);

    expect(Gate::forUser($scenario['seller'])->allows('view', $receiptForSeller))->toBeTrue()
        ->and(Gate::forUser($scenario['seller'])->allows('view', $receiptForOtherSeller))->toBeFalse();
});

it('allows a collector to see only assigned customers', function () {
    $tenant = tenantContext();
    $collector = memberFor($tenant, TenantRole::Collector);
    $otherCollector = memberFor($tenant, TenantRole::Collector);

    $assigned = Customer::factory()->create(['collector_user_id' => $collector->id]);
    $foreign = Customer::factory()->create(['collector_user_id' => $otherCollector->id]);
    $assignedInvoice = Invoice::factory()->create(['customer_id' => $assigned->id]);
    $foreignInvoice = Invoice::factory()->create(['customer_id' => $foreign->id]);

    expect(Gate::forUser($collector)->allows('view', $assignedInvoice))->toBeTrue()
        ->and(Gate::forUser($collector)->allows('view', $foreignInvoice))->toBeFalse();
});

it('prevents sellers from matching collections', function () {
    $scenario = policyScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    expect(Gate::forUser($scenario['seller'])->allows('match', CollectionEvent::factory()->create([
        'invoice_id' => $scenario['invoice']->id,
        'seller_user_id' => $scenario['seller']->id,
    ])))->toBeFalse()
        ->and(Gate::forUser($scenario['supervisor'])->allows('match', CollectionEvent::factory()->create([
            'invoice_id' => $scenario['invoice']->id,
            'seller_user_id' => $scenario['seller']->id,
        ])))->toBeTrue();
});

it('prevents sellers from approving cash sessions and allows supervisors', function () {
    $scenario = policyScenario();
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $session = CashSession::factory()->submitted()->create([
        'seller_user_id' => $scenario['seller']->id,
    ]);

    expect(Gate::forUser($scenario['seller'])->allows('approve', $session))->toBeFalse()
        ->and(Gate::forUser($scenario['supervisor'])->allows('approve', $session))->toBeTrue();
});

it('does not allow anyone to delete invoices, customers or audit events', function () {
    $scenario = policyScenario();
    $admin = memberFor($scenario['tenant'], TenantRole::Admin);
    $audit = AuditEvent::factory()->create(['tenant_id' => $scenario['tenant']->id]);

    expect(Gate::forUser($admin)->allows('delete', $scenario['invoice']))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $scenario['customer']))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $audit))->toBeFalse();
});

it('denies access to users without membership', function () {
    $scenario = policyScenario();
    $outsider = User::factory()->create();
    $otherTenant = Tenant::factory()->create();
    memberFor($otherTenant, TenantRole::Admin, $outsider);

    expect(Gate::forUser($outsider)->allows('view', $scenario['invoice']))->toBeFalse();
});
