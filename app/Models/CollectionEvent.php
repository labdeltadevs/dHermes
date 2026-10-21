<?php

namespace App\Models;

use App\Contracts\Verifiable;
use App\Enums\CollectionKind;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CollectionEventFactory;
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
 * @property string $invoice_id
 * @property string $seller_user_id
 * @property string $amount
 * @property string $currency
 * @property string|null $local_amount
 * @property PaymentMethod $payment_method
 * @property Carbon $collected_on
 * @property CollectionKind $kind
 * @property CollectionStatus $status
 * @property string|null $matched_receipt_id
 * @property Carbon|null $matched_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'invoice_id',
    'seller_user_id',
    'amount',
    'currency',
    'local_amount',
    'payment_method',
    'collected_on',
    'kind',
    'status',
    'matched_receipt_id',
    'matched_at',
    'notes',
])]
class CollectionEvent extends Model implements Verifiable
{
    /** @use HasFactory<CollectionEventFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'local_amount' => 'decimal:6',
            'payment_method' => PaymentMethod::class,
            'collected_on' => 'date',
            'kind' => CollectionKind::class,
            'status' => CollectionStatus::class,
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function matchedReceipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'matched_receipt_id');
    }

    /**
     * @return HasMany<CashSessionItem, $this>
     */
    public function cashSessionItems(): HasMany
    {
        return $this->hasMany(CashSessionItem::class);
    }

    public function verificationPayload(): array
    {
        return [
            'type' => 'collection_event',
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'invoice_id' => $this->invoice_id,
            'seller_user_id' => $this->seller_user_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'collected_on' => $this->collected_on->toDateString(),
            'kind' => $this->kind->value,
        ];
    }

    public function verificationLabel(): string
    {
        return 'Registro de cobranza';
    }
}
