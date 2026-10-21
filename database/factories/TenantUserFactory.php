<?php

namespace Database\Factories;

use App\Models\TenantUser;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantUser>
 */
class TenantUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => app(CurrentTenant::class)->requireId(),
            'user_id' => User::factory(),
            'role' => 'seller',
            'sales_rep_id' => null,
            'active' => true,
        ];
    }
}
