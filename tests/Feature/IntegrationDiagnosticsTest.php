<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Support\Integration\IntegrationDiagnostics;

it('reports consistent imported data as clean', function () {
    $tenant = tenantContext();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'total' => 100,
        'paid' => 20,
        'balance' => 80,
    ]);

    $checks = collect(app(IntegrationDiagnostics::class)->run($tenant->id))->keyBy('key');

    expect($checks['invoice_balance']['count'])->toBe(0)
        ->and($checks['receipt_overapplied']['count'])->toBe(0)
        ->and($checks['receipt_application_invtype']['count'])->toBe(0);

    $this->artisan('dhermes:integration-check', ['--tenant' => $tenant->code])
        ->assertExitCode(0)
        ->expectsOutputToContain('Sin errores de integracion.');
});

it('detects balance, overapplication and InvType anomalies', function () {
    $tenant = tenantContext();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'total' => 100,
        'paid' => 10,
        'balance' => 50,
    ]);
    $receipt = Receipt::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'total' => 100,
    ]);

    ReceiptApplication::factory()->create([
        'tenant_id' => $tenant->id,
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'invtype' => '15',
        'applied_amount' => 150,
    ]);

    $checks = collect(app(IntegrationDiagnostics::class)->run($tenant->id))->keyBy('key');

    expect($checks['invoice_balance']['count'])->toBe(1)
        ->and($checks['receipt_overapplied']['count'])->toBe(1)
        ->and($checks['receipt_application_invtype']['count'])->toBe(1);

    $this->artisan('dhermes:integration-check', ['--tenant' => $tenant->code])
        ->assertExitCode(1)
        ->expectsOutputToContain('errores de integracion');
});

it('rejects an unknown tenant option', function () {
    $this->artisan('dhermes:integration-check', ['--tenant' => 'NO-EXISTE'])
        ->assertExitCode(1)
        ->expectsOutputToContain('No se encontro la empresa indicada.');
});
