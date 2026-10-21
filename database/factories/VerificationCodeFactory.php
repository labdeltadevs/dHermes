<?php

namespace Database\Factories;

use App\Models\CollectionEvent;
use App\Models\VerificationCode;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VerificationCode>
 */
class VerificationCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'verifiable_type' => CollectionEvent::class,
            'verifiable_id' => fn (): string => CollectionEvent::factory()->create()->id,
            'code' => Str::random(32),
            'signature' => str_repeat('a', 64),
            'issued_at' => now(),
            'revoked_at' => null,
        ];
    }
}
