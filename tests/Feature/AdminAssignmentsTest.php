<?php

use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\SalesRep;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function assignmentScenario(): array
{
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);
    $collector = memberFor($tenant, TenantRole::Collector);
    $otherCollector = memberFor($tenant, TenantRole::Collector);
    $salesRep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $salesRep->id,
        'name' => 'Cliente Uno',
    ]);

    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $salesRep->id,
        'name' => 'Cliente Dos',
    ]);

    $unassignedCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'sales_rep_id' => $salesRep->id,
        'name' => 'Cliente Sin Cobrador',
    ]);

    return compact(
        'tenant',
        'admin',
        'collector',
        'otherCollector',
        'salesRep',
        'customer',
        'otherCustomer',
        'unassignedCustomer',
    );
}

it('lets an admin assign and unassign a single customer', function () {
    $scenario = assignmentScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.assignments')
        ->call('assignSingle', $scenario['customer']->id, $scenario['collector']->id)
        ->assertHasNoErrors();

    expect($scenario['customer']->fresh()->collector_user_id)->toBe($scenario['collector']->id);

    $assigned = AuditEvent::query()
        ->where('action', 'customer.collector_assigned')
        ->where('entity', 'customer')
        ->firstOrFail();

    expect($assigned->details['customer_ids'])->toContain($scenario['customer']->id)
        ->and($assigned->details['count'])->toBe(1)
        ->and($assigned->details['collector_user_id'])->toBe($scenario['collector']->id)
        ->and($assigned->tenant_id)->toBe($scenario['tenant']->id);

    Livewire::test('pages::admin.assignments')
        ->call('unassignSingle', $scenario['customer']->id)
        ->assertHasNoErrors();

    expect($scenario['customer']->fresh()->collector_user_id)->toBeNull();

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $scenario['tenant']->id,
        'action' => 'customer.collector_unassigned',
        'entity' => 'customer',
    ]);
});

it('lets an admin assign and unassign customers in bulk', function () {
    $scenario = assignmentScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $selected = [$scenario['customer']->id, $scenario['otherCustomer']->id];

    Livewire::test('pages::admin.assignments')
        ->set('selected', $selected)
        ->set('bulkCollectorId', $scenario['collector']->id)
        ->call('assignSelected')
        ->assertHasNoErrors();

    expect($scenario['customer']->fresh()->collector_user_id)->toBe($scenario['collector']->id)
        ->and($scenario['otherCustomer']->fresh()->collector_user_id)->toBe($scenario['collector']->id);

    $event = AuditEvent::query()
        ->where('action', 'customer.collector_assigned')
        ->firstOrFail();

    expect($event->details['count'])->toBe(2)
        ->and($event->details['customer_ids'])->toEqualCanonicalizing($selected);

    Livewire::test('pages::admin.assignments')
        ->set('selected', $selected)
        ->call('unassignSelected')
        ->assertHasNoErrors();

    expect($scenario['customer']->fresh()->collector_user_id)->toBeNull()
        ->and($scenario['otherCustomer']->fresh()->collector_user_id)->toBeNull();
});

it('forbids supervisors and outsiders from managing assignments', function () {
    $scenario = assignmentScenario();
    $supervisor = memberFor($scenario['tenant'], TenantRole::Supervisor);

    $this->actingAs($supervisor)
        ->get(route('admin.assignments'))
        ->assertForbidden();

    $outsider = memberFor($scenario['tenant'], TenantRole::Seller);

    $this->actingAs($outsider)
        ->get(route('admin.assignments'))
        ->assertForbidden();
});

it('rejects collectors from another tenant', function () {
    $scenario = assignmentScenario();

    $otherTenant = tenantContext(['code' => 'AJENA']);
    $foreignCollector = memberFor($otherTenant, TenantRole::Collector);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::admin.assignments')
        ->call('assignSingle', $scenario['customer']->id, $foreignCollector->id)
        ->assertHasErrors('collector');

    expect($scenario['customer']->fresh()->collector_user_id)->toBeNull();

    $this->assertDatabaseMissing('audit_events', [
        'tenant_id' => $scenario['tenant']->id,
        'action' => 'customer.collector_assigned',
    ]);
});

it('lists only the current tenant customers and collectors', function () {
    $scenario = assignmentScenario();

    $otherTenant = tenantContext(['code' => 'AJENA']);
    $foreignCustomer = Customer::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Cliente Ajeno',
    ]);
    $foreignCollector = memberFor($otherTenant, TenantRole::Collector);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::admin.assignments')
        ->assertSee('Cliente Uno')
        ->assertSee($scenario['collector']->email)
        ->assertDontSee('Cliente Ajeno')
        ->assertDontSee($foreignCollector->email);

    expect($foreignCustomer->tenant_id)->not->toBe($scenario['tenant']->id);
});

it('filters assignments by collector and search', function () {
    $scenario = assignmentScenario();

    $scenario['customer']->forceFill(['collector_user_id' => $scenario['collector']->id])->save();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.assignments')
        ->set('collectorFilter', 'unassigned')
        ->assertSee('Cliente Dos')
        ->assertDontSee('Cliente Uno')
        ->set('collectorFilter', '')
        ->set('search', 'Cliente Uno')
        ->assertSee('Cliente Uno')
        ->assertDontSee('Cliente Dos');
});

it('paginates assignments with numbered page links', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    Customer::factory()->count(30)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.assignments')
        ->assertSee('Mostrando 1 a 25 de 30 resultados')
        ->assertSeeHtml('aria-label="Ir a la página 2"')
        ->call('gotoPage', 2)
        ->assertSee('Mostrando 26 a 30 de 30 resultados')
        ->assertSeeHtml('aria-current="page"');
});
