<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptApplication;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptApplication>
 */
class ReceiptApplicationFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 5000);

        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'receipt_id' => fn (): string => Receipt::factory()->create()->id,
            'invoice_id' => fn (): string => Invoice::factory()->create()->id,
            'docnum' => fake()->numerify('######'),
            'invoiceid' => fake()->unique()->numberBetween(1, 999999),
            'docentry' => fake()->numerify('######'),
            'instid' => fake()->numberBetween(1, 99),
            'invtype' => '13',
            'applied_amount' => $amount,
            'local_applied_amount' => $amount,
            'application_date' => now()->toDateString(),
            'synced_at' => now(),
        ];
    }
}
