<?php

namespace App\Models;

use App\Enums\ActorKind;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $actor_user_id
 * @property ActorKind $actor_kind
 * @property string $action
 * @property string $entity
 * @property string|null $entity_id
 * @property Carbon $occurred_at
 * @property array<string, mixed> $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'actor_user_id',
    'actor_kind',
    'action',
    'entity',
    'entity_id',
    'occurred_at',
    'details',
])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'actor_kind' => ActorKind::class,
            'occurred_at' => 'datetime',
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
