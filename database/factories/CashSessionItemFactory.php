<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\CashSessionItem;
use App\Models\CollectionEvent;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSessionItem>
 */
class CashSessionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'cash_session_id' => fn (): string => CashSession::factory()->create()->id,
            'collection_event_id' => fn (): string => CollectionEvent::factory()->create()->id,
            'payment_method' => PaymentMethod::Cash,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'reference' => null,
        ];
    }
}
