<?php

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Http\Controllers\VerificationPrintController;
use App\Models\AuditEvent;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Verification\IssueVerificationCode;
use App\Support\Verification\QrCode;
use App\Support\Verification\RevokeVerificationCode;
use App\Support\Verification\VerificationResult;
use App\Support\Verification\VerifyCode;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function registerVerificationPrintRoute(): void
{
    if (! app('router')->has('verification.print')) {
        app('router')->get('/verificar/{code}/comprobante', VerificationPrintController::class)
            ->where('code', '[A-Za-z0-9]+')
            ->middleware('auth')
            ->name('verification.print');

        app('router')->getRoutes()->refreshNameLookups();
    }
}

function verificationScenario(): array
{
    $tenant = tenantContext();
    $rep = SalesRep::factory()->create(['tenant_id' => $tenant->id]);
    $seller = memberFor($tenant, TenantRole::Seller, salesRep: $rep);
    $supervisor = memberFor($tenant, TenantRole::Supervisor);
    $customer = Customer::factory()->create(['sales_rep_id' => $rep->id]);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'sales_rep_id' => $rep->id,
    ]);

    $event = CollectionEvent::factory()->create([
        'invoice_id' => $invoice->id,
        'seller_user_id' => $seller->id,
        'amount' => 250,
        'currency' => 'BOB',
        'local_amount' => 250,
        'payment_method' => PaymentMethod::Cash,
        'collected_on' => now()->toDateString(),
        'kind' => CollectionKind::Partial,
        'status' => CollectionStatus::Accepted,
        'erp_docnum' => '100341331',
        'reviewed_at' => now(),
    ]);

    return compact('tenant', 'rep', 'seller', 'supervisor', 'customer', 'invoice', 'event');
}

it('does not issue a verification code for a collection that is not accepted', function () {
    $scenario = verificationScenario();
    $scenario['event']->update(['status' => CollectionStatus::Pending]);
    $this->actingAs($scenario['seller']);

    expect(fn () => app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']))
        ->toThrow(AuthorizationException::class);

    $scenario['event']->update(['status' => CollectionStatus::Rejected]);
    $this->actingAs($scenario['supervisor']);

    expect(fn () => app(IssueVerificationCode::class)->handle($scenario['supervisor'], $scenario['event']->refresh()))
        ->toThrow(AuthorizationException::class);

    expect(VerificationCode::query()->count())->toBe(0);
});

it('issues a verification code signed with the record payload', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    expect($code->tenant_id)->toBe($scenario['tenant']->id)
        ->and($code->verifiable_id)->toBe($scenario['event']->id)
        ->and($code->signature)->toHaveLength(64)
        ->and(app(VerifyCode::class)->handle($code->code))->toBe(VerificationResult::Valid);
});

it('writes an audit event when issuing a verification code', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $scenario['tenant']->id,
        'actor_user_id' => $scenario['seller']->id,
        'action' => 'verification.issued',
        'entity' => VerificationCode::class,
        'entity_id' => $code->id,
    ]);
});

it('returns the existing active code instead of issuing a duplicate', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $first = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);
    $second = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    expect($second->id)->toBe($first->id)
        ->and($second->code)->toBe($first->code)
        ->and(VerificationCode::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'verification.issued')->count())->toBe(1);
});

it('revokes a code, writes an audit event and invalidates public verification', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $this->actingAs($scenario['supervisor']);

    $revoked = app(RevokeVerificationCode::class)->handle($scenario['supervisor'], $code);

    expect($revoked->revoked_at)->not->toBeNull()
        ->and(app(VerifyCode::class)->handle($code->code))->toBe(VerificationResult::Revoked);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $scenario['tenant']->id,
        'actor_user_id' => $scenario['supervisor']->id,
        'action' => 'verification.revoked',
        'entity' => VerificationCode::class,
        'entity_id' => $code->id,
    ]);

    $this->withoutVite()
        ->get(route('verification.show', $code->code))
        ->assertOk()
        ->assertSee('revocado');
});

it('generates an svg qr code', function () {
    $svg = app(QrCode::class)->svg('https://example.test/verificar/abc123');

    expect($svg)->toStartWith('<svg')
        ->and($svg)->toContain('</svg>');
});

it('detects tampered records', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $scenario['event']->update(['amount' => 999]);

    expect(app(VerifyCode::class)->handle($code->code))->toBe(VerificationResult::Tampered);
});

it('detects revoked codes', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);
    $code->update(['revoked_at' => now()]);

    expect(app(VerifyCode::class)->handle($code->code))->toBe(VerificationResult::Revoked);
});

it('reports unknown codes', function () {
    expect(app(VerifyCode::class)->handle('does-not-exist'))->toBe(VerificationResult::NotFound);
});

it('prevents unrelated users from issuing codes', function () {
    $scenario = verificationScenario();
    $otherTenant = tenantContext();
    $outsider = memberFor($otherTenant, TenantRole::Seller);

    app(CurrentTenant::class)->set($scenario['tenant']->id);
    $this->actingAs($outsider);

    expect(fn () => app(IssueVerificationCode::class)->handle($outsider, $scenario['event']))
        ->toThrow(AuthorizationException::class);
});

it('serves the public verification page without authentication', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $this->withoutVite()
        ->get(route('verification.show', $code->code))
        ->assertOk()
        ->assertSee('Comprobante válido')
        ->assertSee('Registro de cobranza')
        ->assertSee('BOB')
        ->assertSee('250,00');
});

it('shows tampered and revoked states on the public page', function () {
    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);
    $scenario['event']->update(['amount' => 999]);

    $this->withoutVite()
        ->get(route('verification.show', $code->code))
        ->assertOk()
        ->assertSee('alterado');

    $code->update(['revoked_at' => now()]);

    $this->withoutVite()
        ->get(route('verification.show', $code->code))
        ->assertOk()
        ->assertSee('revocado');
});

it('returns not found for unknown verification codes', function () {
    $this->withoutVite()
        ->get(route('verification.show', 'unknowncode'))
        ->assertNotFound();
});

it('prevents anonymous users from issuing codes', function () {
    $scenario = verificationScenario();

    expect(fn () => app(IssueVerificationCode::class)->handle(new User, $scenario['event']))
        ->toThrow(AuthorizationException::class);
});

it('requires authentication to print a verification receipt', function () {
    registerVerificationPrintRoute();

    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    auth()->logout();

    $this->get(route('verification.print', $code->code))
        ->assertRedirect(route('login'));
});

it('prints a verification receipt for the owner and approvers', function () {
    registerVerificationPrintRoute();

    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $this->withoutVite()
        ->get(route('verification.print', $code->code))
        ->assertOk()
        ->assertSee('Imprimir')
        ->assertSee($code->code)
        ->assertSee('Comprobante de registro de cobranza')
        ->assertSee('BOB')
        ->assertSee(route('verification.show', $code->code))
        ->assertSee('<svg', false);

    $this->actingAs($scenario['supervisor']);

    $this->withoutVite()
        ->get(route('verification.print', $code->code))
        ->assertOk();
});

it('returns not found when printing an unknown verification code', function () {
    registerVerificationPrintRoute();

    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $this->get(route('verification.print', 'unknowncode'))
        ->assertNotFound();
});

it('forbids unrelated users from printing a verification receipt', function () {
    registerVerificationPrintRoute();

    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $otherTenant = tenantContext();
    $outsider = memberFor($otherTenant, TenantRole::Seller);

    $this->actingAs($outsider);

    $this->get(route('verification.print', $code->code))
        ->assertForbidden();
});

it('lets approvers access the verification codes page but not sellers', function () {
    registerVerificationPrintRoute();

    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $this->actingAs($scenario['supervisor']);

    $this->withoutVite()
        ->get(route('admin.verification-codes'))
        ->assertOk()
        ->assertSee($code->code);

    $this->actingAs($scenario['seller']);

    $this->get(route('admin.verification-codes'))
        ->assertForbidden();
});

it('revokes a verification code from the admin page', function () {
    registerVerificationPrintRoute();

    $scenario = verificationScenario();
    $this->actingAs($scenario['seller']);

    $code = app(IssueVerificationCode::class)->handle($scenario['seller'], $scenario['event']);

    $this->actingAs($scenario['supervisor']);
    app(CurrentTenant::class)->set($scenario['tenant']->id);

    Livewire::test('pages::admin.verification-codes')
        ->call('revoke', $code->id)
        ->assertHasNoErrors()
        ->assertSee('Comprobante revocado.');

    expect($code->refresh()->isRevoked())->toBeTrue();

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $scenario['tenant']->id,
        'action' => 'verification.revoked',
        'entity_id' => $code->id,
    ]);
});
