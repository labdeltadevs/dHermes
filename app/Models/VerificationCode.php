<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VerificationCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $verifiable_type
 * @property string $verifiable_id
 * @property string $code
 * @property string $signature
 * @property Carbon $issued_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Model|null $verifiable
 */
#[Fillable([
    'tenant_id',
    'verifiable_type',
    'verifiable_id',
    'code',
    'signature',
    'issued_at',
    'revoked_at',
])]
class VerificationCode extends Model
{
    /** @use HasFactory<VerificationCodeFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
