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
            'erp_docnum' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'notes' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CollectionStatus::Accepted,
            'erp_docnum' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CollectionStatus::Rejected,
            'rejection_reason' => 'Datos del cobro inconsistentes.',
            'reviewed_at' => now(),
        ]);
    }
}
