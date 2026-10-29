@php
    $impersonatingUser = auth()->user();
    $impersonatedTenantId = $impersonatingUser?->is_platform_admin
        ? session(\App\Support\Tenancy\Impersonation::KEY)
        : null;
    $impersonatedTenant = is_string($impersonatedTenantId) && $impersonatedTenantId !== ''
        ? \App\Models\Tenant::query()->find($impersonatedTenantId)
        : null;
@endphp

@if ($impersonatedTenant !== null)
    <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 sm:flex-row sm:items-center sm:justify-between"
        role="status">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <p>
                Estás gestionando <span class="font-semibold">{{ $impersonatedTenant->name }}</span>
                como administrador de plataforma. Todas las acciones quedan auditadas.
            </p>
        </div>

        <form method="POST" action="{{ route('platform.impersonation.exit') }}" class="shrink-0">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm w-full sm:w-auto">
                Volver a plataforma
            </button>
        </form>
    </div>
@endif
