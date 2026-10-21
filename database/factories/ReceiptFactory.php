<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Receipt;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10, 5000);

        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'docentry' => fake()->unique()->numerify('######'),
            'number' => fake()->unique()->numerify('RCT-#####'),
            'customer_id' => fn (): string => Customer::factory()->create()->id,
            'receipt_date' => now()->toDateString(),
            'cancelled' => false,
            'currency' => 'BOB',
            'total' => $total,
            'local_total' => $total,
            'synced_at' => now(),
        ];
    }
}
