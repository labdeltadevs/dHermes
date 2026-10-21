<?php

namespace App\Support\Audit;

use App\Enums\ActorKind;
use App\Models\AuditEvent;
use App\Models\User;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        string $tenantId,
        string $action,
        string $entity,
        ?string $entityId = null,
        array $details = [],
        ?User $actor = null,
    ): AuditEvent {
        return AuditEvent::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->id,
            'actor_kind' => $actor === null ? ActorKind::System : ActorKind::User,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'occurred_at' => now(),
            'details' => $details,
        ]);
    }
}
