<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $auth_subject
 * @property string|null $password_hash
 * @property bool $active
 * @property bool $is_platform_admin
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'auth_subject', 'password_hash', 'active', 'is_platform_admin'])]
#[Hidden(['password_hash', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, TwoFactorAuthenticatable;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'active' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return Collection<int, TenantUser>
     */
    public function activeMemberships(): Collection
    {
        return $this->tenantUsers()
            ->withoutGlobalScope('tenant')
            ->with('tenant')
            ->where('active', true)
            ->whereHas('tenant', fn (Builder $query): Builder => $query->where('active', true))
            ->get()
            ->sortBy(fn (TenantUser $membership): string => Str::lower($membership->tenant->name))
            ->values();
    }

    public function membershipFor(Tenant|string $tenant): ?TenantUser
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenantUsers()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->first();
    }

    public function hasRoleIn(Tenant|string $tenant, TenantRole ...$roles): bool
    {
        $membership = $this->membershipFor($tenant);

        return $membership !== null && in_array($membership->role, $roles, true);
    }

    public function currentTenantId(): ?string
    {
        return app(CurrentTenant::class)->id();
    }

    public function currentMembership(): ?TenantUser
    {
        $tenantId = $this->currentTenantId();

        return $tenantId === null ? null : $this->membershipFor($tenantId);
    }

    public function canAccessCustomer(Customer $customer): bool
    {
        $membership = $this->membershipFor($customer->tenant_id);

        if ($membership === null) {
            return false;
        }

        if ($membership->role->canApprove()) {
            return true;
        }

        if ($membership->role === TenantRole::Seller) {
            return $membership->sales_rep_id !== null
                && $membership->sales_rep_id === $customer->sales_rep_id;
        }

        if ($membership->role === TenantRole::Collector) {
            return $customer->collector_user_id === $this->id;
        }

        return false;
    }

    public function canAccessInvoice(Invoice $invoice): bool
    {
        $membership = $this->membershipFor($invoice->tenant_id);

        if ($membership === null) {
            return false;
        }

        if ($membership->role->canApprove()) {
            return true;
        }

        if ($membership->role === TenantRole::Seller) {
            return $membership->sales_rep_id !== null
                && $membership->sales_rep_id === $invoice->sales_rep_id;
        }

        if ($membership->role === TenantRole::Collector) {
            return $invoice->customer?->collector_user_id === $this->id;
        }

        return false;
    }

    public function canAccessReceipt(Receipt $receipt): bool
    {
        $membership = $this->membershipFor($receipt->tenant_id);

        if ($membership === null) {
            return false;
        }

        if ($membership->role->canApprove()) {
            return true;
        }

        if ($membership->role === TenantRole::Seller) {
            return $membership->sales_rep_id !== null
                && $receipt->applications()
                    ->whereHas('invoice', fn (Builder $invoice): Builder => $invoice
                        ->where('sales_rep_id', $membership->sales_rep_id))
                    ->exists();
        }

        if ($membership->role === TenantRole::Collector) {
            return $receipt->customer?->collector_user_id === $this->id;
        }

        return false;
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
