<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        abort_if($user === null, 403);

        $membership = $user->membershipFor($validated['tenant_id']);

        abort_if($membership === null || ! $membership->tenant->active, 403);

        $request->session()->put('tenant_id', $membership->tenant_id);

        app(CurrentTenant::class)->set($membership->tenant_id);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Empresa cambiada a '.$membership->tenant->name.'.');
    }
}
