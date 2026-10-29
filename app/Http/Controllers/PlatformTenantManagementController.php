<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PlatformTenantManagementController extends Controller
{
    public function manage(Request $request, Tenant $tenant, Impersonation $impersonation): RedirectResponse
    {
        $user = $this->platformAdmin($request);

        abort_unless($tenant->active, 403);

        $impersonation->enter($request, $user, $tenant);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Estás gestionando '.$tenant->name.' como administrador de plataforma.');
    }

    public function exit(Request $request, Impersonation $impersonation): RedirectResponse
    {
        $user = $this->platformAdmin($request);

        $impersonation->exit($request, $user);

        return redirect()
            ->route('admin.tenants')
            ->with('status', 'Volviste al panel de plataforma.');
    }

    private function platformAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->active && $user->is_platform_admin, 403);

        return $user;
    }
}
