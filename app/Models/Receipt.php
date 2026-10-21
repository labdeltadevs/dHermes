<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ReceiptFactory;
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
 * @property string $docentry
 * @property string $number
 * @property string $customer_id
 * @property Carbon $receipt_date
 * @property bool $cancelled
 * @property string $currency
 * @property string $total
 * @property string|null $local_total
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'docentry',
    'number',
    'customer_id',
    'receipt_date',
    'cancelled',
    'currency',
    'total',
    'local_total',
    'synced_at',
])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'cancelled' => 'boolean',
            'total' => 'decimal:6',
            'local_total' => 'decimal:6',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<ReceiptApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(ReceiptApplication::class);
    }

    /**
     * @return HasMany<CollectionEvent, $this>
     */
    public function collectionEvents(): HasMany
    {
        return $this->hasMany(CollectionEvent::class, 'matched_receipt_id');
    }
}
