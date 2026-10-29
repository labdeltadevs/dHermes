<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = app(CurrentTenant::class);
        $currentTenant->clear();

        $user = $request->user();

        if ($user !== null && $user->is_platform_admin) {
            $impersonatedTenantId = (string) $request->session()->get(Impersonation::KEY, '');

            if ($impersonatedTenantId !== '') {
                $tenant = Tenant::query()
                    ->whereKey($impersonatedTenantId)
                    ->where('active', true)
                    ->first();

                if ($tenant !== null) {
                    $currentTenant->set($tenant->id);
                    $request->session()->put('tenant_id', $tenant->id);

                    return $next($request);
                }

                $request->session()->forget([Impersonation::KEY, 'tenant_id']);
            }
        }

        if ($user !== null) {
            $memberships = $user->activeMemberships();
            $selectedTenantId = (string) $request->session()->get('tenant_id', '');

            $membership = $memberships->firstWhere('tenant_id', $selectedTenantId)
                ?? $memberships->first();

            if ($membership !== null) {
                $currentTenant->set($membership->tenant_id);
                $request->session()->put('tenant_id', $membership->tenant_id);
            } else {
                $request->session()->forget('tenant_id');
            }
        }

        return $next($request);
    }
}
