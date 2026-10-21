<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFactory;
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
 * @property string|null $series
 * @property string $customer_id
 * @property string|null $sales_rep_id
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property InvoiceStatus $status
 * @property bool $cancelled
 * @property string $currency
 * @property string $total
 * @property string $paid
 * @property string $balance
 * @property string|null $local_currency
 * @property string|null $local_total
 * @property string|null $local_paid
 * @property string|null $local_balance
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'docentry',
    'number',
    'series',
    'customer_id',
    'sales_rep_id',
    'issue_date',
    'due_date',
    'status',
    'cancelled',
    'currency',
    'total',
    'paid',
    'balance',
    'local_currency',
    'local_total',
    'local_paid',
    'local_balance',
    'synced_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'cancelled' => 'boolean',
            'total' => 'decimal:6',
            'paid' => 'decimal:6',
            'balance' => 'decimal:6',
            'local_total' => 'decimal:6',
            'local_paid' => 'decimal:6',
            'local_balance' => 'decimal:6',
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
     * @return BelongsTo<SalesRep, $this>
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    /**
     * @return HasMany<CollectionEvent, $this>
     */
    public function collectionEvents(): HasMany
    {
        return $this->hasMany(CollectionEvent::class);
    }

    /**
     * @return HasMany<ReceiptApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(ReceiptApplication::class);
    }

    public function isOpen(): bool
    {
        return ! $this->cancelled && $this->balance > 0;
    }
}
