<?php

use App\Enums\ActorKind;
use App\Enums\TenantRole;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Livewire\Livewire;

function auditScenario(): array
{
    $tenant = tenantContext();
    $adminUser = User::factory()->create(['email' => 'auditor@dhermes.test']);
    $otherUser = User::factory()->create(['email' => 'otro@dhermes.test']);
    $admin = memberFor($tenant, TenantRole::Admin, $adminUser);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    $seller = memberFor($tenant, TenantRole::Seller);

    $event = AuditEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'actor_user_id' => $adminUser->id,
        'actor_kind' => ActorKind::User,
        'action' => 'invoice.created',
        'entity' => 'invoice',
        'entity_id' => 'invoice-1',
        'occurred_at' => now()->subDay(),
        'details' => ['number' => 'INV-001', 'total' => 100],
    ]);

    $otherEvent = AuditEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'actor_user_id' => $otherUser->id,
        'actor_kind' => ActorKind::User,
        'action' => 'customer.updated',
        'entity' => 'customer',
        'entity_id' => 'customer-1',
        'occurred_at' => now(),
        'details' => ['name' => 'Cliente'],
    ]);

    return compact('tenant', 'admin', 'supervisor', 'seller', 'adminUser', 'otherUser', 'event', 'otherEvent');
}

it('lets admins and supervisors view the audit log', function () {
    $scenario = auditScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.audit')
        ->assertOk()
        ->assertSee('invoice.created')
        ->assertSee('customer.updated')
        ->assertSee('auditor@dhermes.test')
        ->assertSee('Ver detalles');

    $this->actingAs($scenario['supervisor']);

    Livewire::test('pages::admin.audit')
        ->assertOk()
        ->assertSee('invoice.created');
});

it('forbids sellers from viewing the audit log', function () {
    $scenario = auditScenario();

    $this->actingAs($scenario['seller'])
        ->get(route('admin.audit'))
        ->assertForbidden();
});

it('filters the audit log by action and entity', function () {
    $scenario = auditScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.audit')
        ->set('action', 'invoice')
        ->assertSee('invoice.created')
        ->assertDontSee('customer.updated')
        ->set('action', '')
        ->set('entity', 'customer')
        ->assertSee('customer.updated')
        ->assertDontSee('invoice.created');
});

it('filters the audit log by actor', function () {
    $scenario = auditScenario();

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.audit')
        ->set('actor', 'auditor@dhermes.test')
        ->assertSee('invoice.created')
        ->assertDontSee('customer.updated')
        ->set('actor', $scenario['otherUser']->id)
        ->assertSee('customer.updated')
        ->assertDontSee('invoice.created');
});

it('filters the audit log by occurred date range', function () {
    $scenario = auditScenario();

    $oldEvent = AuditEvent::factory()->create([
        'tenant_id' => $scenario['tenant']->id,
        'action' => 'old.event',
        'occurred_at' => now()->subDays(20),
    ]);

    $this->actingAs($scenario['admin']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.audit')
        ->set('from', now()->subDays(3)->toDateString())
        ->assertSee('invoice.created')
        ->assertDontSee('old.event')
        ->set('from', '')
        ->set('to', now()->subDays(10)->toDateString())
        ->assertSee('old.event')
        ->assertDontSee('invoice.created');

    expect($oldEvent->occurred_at->lt(now()->subDays(10)))->toBeTrue();
});

it('only lists audit events from the current tenant', function () {
    $scenario = auditScenario();

    $otherTenant = tenantContext(['code' => 'AJENA']);
    AuditEvent::factory()->create([
        'tenant_id' => $otherTenant->id,
        'action' => 'foreign.event',
    ]);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    $this->actingAs($scenario['admin']);

    Livewire::test('pages::admin.audit')
        ->assertSee('invoice.created')
        ->assertDontSee('foreign.event');
});

it('paginates the audit log with numbered page links', function () {
    $tenant = tenantContext();
    $admin = memberFor($tenant, TenantRole::Admin);

    AuditEvent::factory()->count(30)->create([
        'tenant_id' => $tenant->id,
        'action' => 'bulk.event',
    ]);

    $this->actingAs($admin);
    app(CurrentTenant::class)->set($tenant->id);

    Livewire::test('pages::admin.audit')
        ->assertSee('Mostrando 1 a 25 de 30 resultados')
        ->assertSeeHtml('aria-label="Ir a la página 2"')
        ->call('gotoPage', 2)
        ->assertSee('Mostrando 26 a 30 de 30 resultados')
        ->assertSeeHtml('aria-current="page"');
});
