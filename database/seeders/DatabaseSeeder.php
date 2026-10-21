<?php

namespace Database\Seeders;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Empresa Demo',
                'tax_id' => '000000000',
                'timezone' => 'America/La_Paz',
                'local_currency' => 'BOB',
                'active' => true,
            ],
        );

        app(CurrentTenant::class)->set($tenant->id);

        $salesRep = SalesRep::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slpcode' => '1'],
            ['name' => 'Vendedor Demo', 'email' => 'vendedor@dhermes.test', 'active' => true],
        );

        $this->seedMember($tenant, 'admin@dhermes.test', 'Administrador', TenantRole::Admin);
        $this->seedMember($tenant, 'supervisor@dhermes.test', 'Supervisor', TenantRole::Supervisor);
        $this->seedMember($tenant, 'vendedor@dhermes.test', 'Vendedor', TenantRole::Seller, $salesRep);

        $customer = Customer::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'cardcode' => 'C00001'],
            [
                'name' => 'Cliente Demo',
                'sales_rep_id' => $salesRep->id,
                'currency' => 'BOB',
                'credit_limit' => 10000,
                'active' => true,
            ],
        );

        Invoice::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'docentry' => '1001'],
            [
                'number' => 'INV-1001',
                'series' => 'INV',
                'customer_id' => $customer->id,
                'sales_rep_id' => $salesRep->id,
                'issue_date' => now()->subDays(15)->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'currency' => 'BOB',
                'total' => 1000,
                'paid' => 0,
                'balance' => 1000,
                'local_currency' => 'BOB',
                'local_total' => 1000,
                'local_paid' => 0,
                'local_balance' => 1000,
                'synced_at' => now(),
            ],
        );
    }

    private function seedMember(
        Tenant $tenant,
        string $email,
        string $name,
        TenantRole $role,
        ?SalesRep $salesRep = null,
    ): void {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password_hash' => 'password',
                'active' => true,
            ],
        );

        TenantUser::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'sales_rep_id' => $salesRep?->id,
                'active' => true,
            ],
        );
    }
}
