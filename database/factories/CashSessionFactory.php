<?php

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'seller_user_id' => User::factory(),
            'opened_at' => now(),
            'closed_at' => null,
            'status' => CashSessionStatus::Open,
            'currency' => 'BOB',
            'expected_amount' => 0,
            'counted_amount' => null,
            'difference' => null,
            'notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashSessionStatus::Submitted,
            'closed_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashSessionStatus::Approved,
            'closed_at' => now(),
            'approved_at' => now(),
        ]);
    }
}
