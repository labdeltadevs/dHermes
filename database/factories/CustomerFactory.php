<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'cardcode' => 'C'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'tax_id' => fake()->numerify('#########'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'sales_rep_id' => null,
            'collector_user_id' => null,
            'currency' => 'BOB',
            'credit_limit' => fake()->randomFloat(2, 0, 100000),
            'active' => true,
        ];
    }
}
