<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;

final class Impersonation
{
    public const KEY = 'platform_impersonation';

    public function tenantId(Request $request): ?string
    {
        $tenantId = $request->session()->get(self::KEY);

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }

    public function tenant(Request $request): ?Tenant
    {
        $tenantId = $this->tenantId($request);

        return $tenantId === null
            ? null
            : Tenant::query()->whereKey($tenantId)->where('active', true)->first();
    }

    public function enter(Request $request, User $user, Tenant $tenant): void
    {
        $request->session()->put(self::KEY, $tenant->id);
        $request->session()->put('tenant_id', $tenant->id);

        app(CurrentTenant::class)->set($tenant->id);

        app(AuditLogger::class)->record(
            $tenant->id,
            'platform.impersonation.started',
            'tenant',
            $tenant->id,
            [
                'code' => $tenant->code,
                'name' => $tenant->name,
            ],
            $user,
        );
    }

    public function exit(Request $request, User $user): void
    {
        $tenantId = $this->tenantId($request);

        if ($tenantId !== null) {
            app(AuditLogger::class)->record(
                $tenantId,
                'platform.impersonation.ended',
                'tenant',
                $tenantId,
                [],
                $user,
            );
        }

        $request->session()->forget([self::KEY, 'tenant_id']);

        app(CurrentTenant::class)->clear();
    }

    public function isImpersonating(Request $request): bool
    {
        return $this->tenantId($request) !== null;
    }
}
