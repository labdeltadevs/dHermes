<?php

use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Inicio')] class extends Component
{
    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        return $this->view()->with([
            'membership' => $membership,
            'tenant' => $membership === null ? null : Tenant::find($membership->tenant_id),
            'canApprove' => $membership?->role->canApprove() ?? false,
            'canManageTenant' => $membership?->role->canManageTenant() ?? false,
        ]);
    }
};
?>

<div class="space-y-6 sm:space-y-8">
    <section class="relative overflow-hidden rounded-3xl border border-black/10 bg-white p-5 shadow-sm sm:p-8">
        <div class="pointer-events-none absolute -right-20 -top-24 size-64 rounded-full bg-brand-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 size-56 rounded-full bg-brand-500/5 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700">
                        <span class="size-1.5 rounded-full bg-brand-500"></span>
                        {{ $tenant?->name ?? 'Sin empresa activa' }}
                    </span>
                    <span class="text-xs font-medium text-black/60">{{ now()->format('d/m/Y') }}</span>
                </div>

                <h1 class="mt-4 text-2xl font-semibold tracking-[-0.03em] text-ink sm:text-4xl">
                    Hola, {{ auth()->user()->name }}
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-black/60 sm:text-base">
                    Este es el punto de partida para la gestión de cartera, cobros y arqueos de {{ $tenant?->code ?? 'tu empresa' }}.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="rounded-full border border-black/10 bg-canvas px-3 py-1.5 text-xs font-semibold text-black/60">
                        {{ $membership?->role->label() }}
                    </span>
                    <span class="rounded-full border border-black/10 bg-canvas px-3 py-1.5 text-xs font-semibold text-black/60">
                        {{ $tenant?->local_currency ?? '—' }}
                    </span>
                    <span class="rounded-full border border-black/10 bg-canvas px-3 py-1.5 text-xs font-semibold text-black/60">
                        {{ $tenant?->timezone ?? '—' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <a href="{{ route('portfolio') }}" wire:navigate
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-brand-400 focus:outline-none focus:ring-4 focus:ring-brand-500/25">
                    Ver cartera
                </a>

                @if ($membership?->role->sells())
                    <a href="{{ route('collections.create') }}" wire:navigate
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                        Registrar cobro
                    </a>
                @endif

                @if ($canManageTenant)
                    <a href="{{ route('admin.users') }}" wire:navigate
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                        Gestionar usuarios
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section>
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Operación diaria</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight text-ink">Módulos</h2>
            </div>
            <p class="text-sm text-black/60">Accesos disponibles según tu rol.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (array_values(array_filter([
                ['title' => 'Cartera', 'description' => 'Facturas pendientes y saldos por cliente.', 'icon' => 'M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Z', 'route' => route('portfolio')],
                ['title' => 'Cobros', 'description' => 'Comprobantes de cobro y aplicaciones de SAP.', 'icon' => 'M12 6v12m-4-9h8M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z', 'route' => route('receipts')],
                ['title' => 'Cobranzas', 'description' => 'Registro y conciliación de cobros informados.', 'icon' => 'M12 6v12m-4-9h8M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z', 'route' => route('collections.index')],
                ['title' => 'Arqueos', 'description' => 'Apertura, cierre y aprobación de cajas.', 'icon' => 'M4 7h16M4 12h16M4 17h10M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z', 'route' => route('cash-sessions.index')],
                $canApprove ? ['title' => 'Cartera por vendedor', 'description' => 'Saldos agrupados por representante de ventas.', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'route' => route('portfolio.by-rep')] : null,
                $canApprove ? ['title' => 'Verificación QR', 'description' => 'Comprobantes verificables de forma pública.', 'icon' => 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 3h3v3M14 14h3', 'route' => route('admin.verification-codes')] : null,
            ])) as $module)
                @php
                    $isAvailable = isset($module['route']);
                    $tag = $isAvailable ? 'a' : 'article';
                @endphp

                <{{ $tag }} wire:key="module-{{ $module['title'] }}"
                    @if ($isAvailable) href="{{ $module['route'] }}" wire:navigate @endif
                    class="group rounded-2xl border border-black/10 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-500 group-hover:text-ink">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $module['icon'] }}" />
                            </svg>
                        </div>
                        <span class="rounded-full {{ $isAvailable ? 'bg-brand-50 text-brand-700' : 'bg-black/[0.04] text-black/60' }} px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide">
                            {{ $isAvailable ? 'Disponible' : 'Próximamente' }}
                        </span>
                    </div>
                    <h3 class="mt-4 text-sm font-semibold text-ink">{{ $module['title'] }}</h3>
                    <p class="mt-1 text-sm leading-6 text-black/60">{{ $module['description'] }}</p>
                </{{ $tag }}>
            @endforeach
        </div>
    </section>

    @if ($canManageTenant || $canApprove)
        <section class="rounded-2xl border border-brand-200 bg-brand-50 p-5 sm:p-6">
            <div>
                <h2 class="text-sm font-semibold text-brand-900">Administración</h2>
                <p class="mt-1 text-sm leading-6 text-brand-800">
                    Gestiona cuentas, cartera asignada y trazabilidad de las operaciones.
                </p>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @if ($canManageTenant)
                    <a href="{{ route('admin.users') }}" wire:navigate
                        class="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-100">
                        Usuarios
                    </a>
                    <a href="{{ route('admin.assignments') }}" wire:navigate
                        class="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-100">
                        Asignaciones
                    </a>
                @endif

                @if ($canApprove)
                    <a href="{{ route('admin.audit') }}" wire:navigate
                        class="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-100">
                        Auditor&iacute;a
                    </a>
                    <a href="{{ route('admin.verification-codes') }}" wire:navigate
                        class="rounded-xl border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-100">
                        Comprobantes
                    </a>
                @endif
            </div>
        </section>
    @endif
</div>
