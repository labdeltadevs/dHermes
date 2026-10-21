<?php

namespace App\Console\Commands;

use App\Enums\TenantRole;
use App\Models\SalesRep;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('tenant:attach-user {tenant : Código del tenant} {email : Correo del usuario} {--role=admin : Rol del usuario (admin, supervisor, seller, collector)} {--sales-rep= : Código SLP del vendedor, requerido para rol seller}')]
#[Description('Adjunta un usuario existente a un tenant')]
class AttachUserToTenant extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantArgument = $this->argument('tenant');
        $emailArgument = $this->argument('email');
        $roleArgument = $this->option('role');

        if (! is_string($tenantArgument) || ! is_string($emailArgument) || ! is_string($roleArgument)) {
            $this->components->error('Argumentos inválidos.');

            return self::FAILURE;
        }

        $tenantCode = Str::upper(trim($tenantArgument));
        $email = Str::lower(trim($emailArgument));
        $role = TenantRole::tryFrom($roleArgument);

        if ($role === null) {
            $this->components->error('Rol inválido. Use admin, supervisor, seller o collector.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->whereRaw('upper(code) = ?', [$tenantCode])
            ->first();

        if ($tenant === null) {
            $this->components->error("No existe el tenant '{$tenantCode}'.");

            return self::FAILURE;
        }

        if (! $tenant->active) {
            $this->components->error("El tenant '{$tenant->name}' está inactivo.");

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $this->components->error("No existe el usuario '{$email}'.");

            return self::FAILURE;
        }

        if (! $user->active) {
            $this->components->error("El usuario '{$user->name}' está inactivo.");

            return self::FAILURE;
        }

        $salesRepId = null;

        if ($role === TenantRole::Seller) {
            $salesRepArgument = $this->option('sales-rep');

            if (! is_string($salesRepArgument)) {
                $this->components->error('El rol seller requiere --sales-rep con el código SLP del vendedor.');

                return self::FAILURE;
            }

            $salesRepCode = trim($salesRepArgument);

            if ($salesRepCode === '') {
                $this->components->error('El rol seller requiere --sales-rep con el código SLP del vendedor.');

                return self::FAILURE;
            }

            $salesRep = SalesRep::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('slpcode', $salesRepCode)
                ->first();

            if ($salesRep === null) {
                $this->components->error("No existe el vendedor SLP '{$salesRepCode}' en {$tenant->name}.");

                return self::FAILURE;
            }

            $salesRepId = $salesRep->id;
        }

        DB::transaction(function () use ($user, $tenant, $role, $salesRepId): void {
            app(CurrentTenant::class)->set($tenant->id);

            $membership = TenantUser::query()
                ->withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership === null) {
                $membership = TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'role' => $role,
                    'sales_rep_id' => $salesRepId,
                    'active' => true,
                ]);
            } else {
                $membership->forceFill([
                    'role' => $role,
                    'sales_rep_id' => $salesRepId,
                    'active' => true,
                ])->save();
            }

            app(AuditLogger::class)->record(
                $tenant->id,
                'user.membership_attached',
                'user',
                $user->id,
                [
                    'email' => $user->email,
                    'role' => $role->value,
                    'sales_rep_id' => $salesRepId,
                ],
            );
        });

        $this->components->info("Usuario {$user->name} adjuntado a {$tenant->name} como {$role->label()}.");

        return self::SUCCESS;
    }
}
