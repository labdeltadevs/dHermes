<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_published' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'created_by' => fn (): string => User::factory()->create()->id,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }
}
