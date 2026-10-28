<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ReceiptApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $receipt_id
 * @property string $invoice_id
 * @property string|null $docnum
 * @property int|null $invoiceid
 * @property string|null $docentry
 * @property int|null $instid
 * @property string $invtype
 * @property string $applied_amount
 * @property string|null $local_applied_amount
 * @property Carbon $application_date
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'receipt_id',
    'invoice_id',
    'docnum',
    'invoiceid',
    'docentry',
    'instid',
    'invtype',
    'applied_amount',
    'local_applied_amount',
    'application_date',
    'synced_at',
])]
class ReceiptApplication extends Model
{
    /** @use HasFactory<ReceiptApplicationFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'invoiceid' => 'integer',
            'instid' => 'integer',
            'applied_amount' => 'decimal:6',
            'local_applied_amount' => 'decimal:6',
            'application_date' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Receipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
