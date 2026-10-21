<?php

namespace App\Models;

use App\Contracts\Verifiable;
use App\Enums\CashSessionStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $seller_user_id
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property CashSessionStatus $status
 * @property string $currency
 * @property string $expected_amount
 * @property string|null $counted_amount
 * @property string|null $difference
 * @property string|null $notes
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'seller_user_id',
    'opened_at',
    'closed_at',
    'status',
    'currency',
    'expected_amount',
    'counted_amount',
    'difference',
    'notes',
    'approved_by',
    'approved_at',
])]
class CashSession extends Model implements Verifiable
{
    /** @use HasFactory<CashSessionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => CashSessionStatus::class,
            'expected_amount' => 'decimal:6',
            'counted_amount' => 'decimal:6',
            'difference' => 'decimal:6',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CashSessionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CashSessionItem::class);
    }

    public function verificationPayload(): array
    {
        return [
            'type' => 'cash_session',
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'seller_user_id' => $this->seller_user_id,
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'currency' => $this->currency,
            'expected_amount' => $this->expected_amount,
            'counted_amount' => $this->counted_amount,
            'difference' => $this->difference,
        ];
    }

    public function verificationLabel(): string
    {
        return 'Arqueo de cobranza';
    }
}
