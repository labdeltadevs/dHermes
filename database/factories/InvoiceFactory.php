<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 100, 10000);

        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'docentry' => fake()->unique()->numerify('######'),
            'number' => fake()->unique()->numerify('INV-#####'),
            'series' => 'INV',
            'customer_id' => fn (): string => Customer::factory()->create()->id,
            'sales_rep_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => InvoiceStatus::Open,
            'cancelled' => false,
            'currency' => 'BOB',
            'total' => $total,
            'paid' => 0,
            'balance' => $total,
            'local_currency' => 'BOB',
            'local_total' => $total,
            'local_paid' => 0,
            'local_balance' => $total,
            'synced_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Closed,
            'paid' => $attributes['total'],
            'balance' => 0,
            'local_paid' => $attributes['total'],
            'local_balance' => 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cancelled' => true,
        ]);
    }
}
