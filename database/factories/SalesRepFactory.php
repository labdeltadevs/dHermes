<?php

namespace Database\Factories;

use App\Models\SalesRep;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesRep>
 */
class SalesRepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'slpcode' => fake()->unique()->numerify('###'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }
}
