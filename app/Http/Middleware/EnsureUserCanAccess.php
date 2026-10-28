<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserCanAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hasTenant = app(CurrentTenant::class)->id() !== null;

        if ($user !== null && (! $user->active || (! $user->is_platform_admin && ! $hasTenant))) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => __('Tu cuenta no tiene acceso activo.')]);
        }

        return $next($request);
    }
}
