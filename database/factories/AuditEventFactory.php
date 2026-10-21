<?php

namespace Database\Factories;

use App\Enums\ActorKind;
use App\Models\AuditEvent;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'actor_user_id' => null,
            'actor_kind' => ActorKind::System,
            'action' => 'test',
            'entity' => 'test',
            'entity_id' => null,
            'occurred_at' => now(),
            'details' => [],
        ];
    }
}
