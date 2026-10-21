<?php

namespace Database\Factories;

use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionEvent>
 */
class CollectionEventFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 5000);

        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'invoice_id' => fn (): string => Invoice::factory()->create()->id,
            'seller_user_id' => User::factory(),
            'amount' => $amount,
            'currency' => 'BOB',
            'local_amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'collected_on' => now()->toDateString(),
            'kind' => CollectionKind::Partial,
            'status' => CollectionStatus::Pending,
            'matched_receipt_id' => null,
            'matched_at' => null,
            'notes' => null,
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CollectionStatus::Matched,
            'matched_at' => now(),
        ]);
    }

    public function discrepancy(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CollectionStatus::Discrepancy,
        ]);
    }
}
