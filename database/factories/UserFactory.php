<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'auth_subject' => null,
            'password_hash' => static::$password ??= Hash::make('password'),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_platform_admin' => true,
        ]);
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password_hash' => null,
            'auth_subject' => 'external-'.Str::uuid(),
        ]);
    }
}
