<?php

namespace App\Models;

use App\Observers\TenantObserver;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $tax_id
 * @property string $timezone
 * @property string $local_currency
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'tax_id', 'timezone', 'local_currency', 'active'])]
#[ObservedBy(TenantObserver::class)]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role', 'active', 'sales_rep_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return HasMany<SalesRep, $this>
     */
    public function salesReps(): HasMany
    {
        return $this->hasMany(SalesRep::class);
    }
}
