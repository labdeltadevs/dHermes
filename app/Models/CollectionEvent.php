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
 * @property string|null $invoice_id
 * @property string|null $flash_invoice_number
 * @property string|null $flash_cardcode
 * @property string|null $flash_customer_name
 * @property string $seller_user_id
 * @property string $amount
 * @property string $currency
 * @property string|null $local_amount
 * @property PaymentMethod $payment_method
 * @property Carbon $collected_on
 * @property CollectionKind $kind
 * @property CollectionStatus $status
 * @property string|null $erp_docnum
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property string|null $voucher_code
 * @property string|null $proof_disk
 * @property string|null $proof_path
 * @property string|null $proof_original_name
 * @property string|null $proof_mime_type
 * @property int|null $proof_size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'invoice_id',
    'flash_invoice_number',
    'flash_cardcode',
    'flash_customer_name',
    'seller_user_id',
    'amount',
    'currency',
    'local_amount',
    'payment_method',
    'collected_on',
    'kind',
    'status',
    'erp_docnum',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
    'notes',
    'voucher_code',
    'proof_disk',
    'proof_path',
    'proof_original_name',
    'proof_mime_type',
    'proof_size',
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
            'reviewed_at' => 'datetime',
            'proof_size' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<CashSessionItem, $this>
     */
    public function cashSessionItems(): HasMany
    {
        return $this->hasMany(CashSessionItem::class);
    }

    public function isFlash(): bool
    {
        return $this->invoice_id === null;
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
