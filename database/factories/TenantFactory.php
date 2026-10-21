<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('EMP###')),
            'name' => fake()->company(),
            'tax_id' => fake()->numerify('#########'),
            'timezone' => 'America/La_Paz',
            'local_currency' => 'BOB',
            'active' => true,
        ];
    }
}
