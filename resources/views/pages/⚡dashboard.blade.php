<?php

use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Inicio')] class extends Component {
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
<div class="space-y-8 sm:space-y-10">
    <section
        class="relative isolate overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-surface via-surface to-brand-50/70 shadow-card">
        <div class="pointer-events-none absolute -right-16 -top-20 size-72 rounded-full bg-brand-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 size-56 rounded-full bg-mint-400/10 blur-3xl"></div>

        <div class="relative grid gap-6 p-5 sm:p-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center lg:p-10">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex max-w-full items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800">
                        <span class="size-1.5 shrink-0 rounded-full bg-brand-500"></span>
                        <span class="truncate">{{ $tenant?->name ?? 'Sin empresa activa' }}</span>
                    </span>

                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3 py-1.5 text-xs font-medium text-muted">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z" />
                        </svg>
                        {{ now()->format('d/m/Y') }}
                    </span>
                </div>

                <h1 class="mt-5 text-2xl font-semibold tracking-[-0.03em] text-ink sm:text-4xl">
                    Hola, {{ auth()->user()->name }}
                </h1>

                <p class="mt-2 max-w-xl text-sm leading-6 text-muted sm:text-base">
                    Este es el punto de partida para la gestión de cartera, cobros y arqueos de
                    {{ $tenant?->code ?? 'tu empresa' }}.
                </p>

                <div class="mt-6 grid gap-2 sm:grid-cols-3">
                    <div class="min-w-0 rounded-xl border border-line bg-surface/75 px-3.5 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Rol</p>
                        <p class="mt-1 truncate text-sm font-semibold text-ink"
                            title="{{ $membership?->role?->label() ?? '—' }}">
                            {{ $membership?->role?->label() ?? '—' }}
                        </p>
                    </div>

                    <div class="min-w-0 rounded-xl border border-line bg-surface/75 px-3.5 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Moneda</p>
                        <p class="mt-1 truncate text-sm font-semibold text-ink">
                            {{ $tenant?->local_currency ?? '—' }}
                        </p>
                    </div>

                    <div class="min-w-0 rounded-xl border border-line bg-surface/75 px-3.5 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Zona horaria</p>
                        <p class="mt-1 truncate text-sm font-semibold text-ink" title="{{ $tenant?->timezone ?? '—' }}">
                            {{ $tenant?->timezone ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl border border-line bg-surface/85 p-4 shadow-sm backdrop-blur sm:p-5">
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">Accesos directos</h2>
                        <p class="mt-0.5 text-xs text-muted">Atajos para tu operación diaria.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-2">
                    <a href="{{ route('portfolio') }}" wire:navigate class="btn btn-primary w-full justify-between">
                        <span class="inline-flex items-center gap-2">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M4 5h7v6H4V5Zm9 0h7v6h-7V5ZM4 13h7v6H4v-6Zm9 0h7v6h-7v-6Z" />
                            </svg>
                            Ver cartera
                        </span>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </a>

                    @if ($membership?->role->sells())
                        <a href="{{ route('collections.create') }}" wire:navigate
                            class="btn btn-outline w-full justify-between">
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M12 8v8m-4-4h8M5 4h14a1 1 0 0 1 1 1v14H4V5a1 1 0 0 1 1-1Z" />
                                </svg>
                                Registrar cobro
                            </span>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M5 12h14m-6-6 6 6-6 6" />
                            </svg>
                        </a>
                    @endif

                    @if ($canManageTenant)
                        <a href="{{ route('admin.users') }}" wire:navigate
                            class="btn btn-outline w-full justify-between">
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                        d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                Gestionar usuarios
                            </span>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M5 12h14m-6-6 6 6-6 6" />
                            </svg>
                        </a>
                    @endif
                </div>
            </aside>
        </div>
    </section>

    @php
        $modules = array_values(
            array_filter([
                [
                    'title' => 'Cartera',
                    'description' => 'Facturas pendientes y saldos por cliente.',
                    'icon' => 'M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Z',
                    'route' => route('portfolio'),
                ],
                [
                    'title' => 'Cobros',
                    'description' => 'Comprobantes de cobro y aplicaciones de SAP.',
                    'icon' => 'M12 6v12m-4-9h8M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z',
                    'route' => route('receipts'),
                ],
                [
                    'title' => 'Cobranzas',
                    'description' => 'Registro y conciliación de cobros informados.',
                    'icon' => 'M12 6v12m-4-9h8M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z',
                    'route' => route('collections.index'),
                ],
                [
                    'title' => 'Arqueos',
                    'description' => 'Apertura, cierre y aprobación de cajas.',
                    'icon' =>
                        'M4 7h16M4 12h16M4 17h10M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z',
                    'route' => route('cash-sessions.index'),
                ],
                $canApprove
                    ? [
                        'title' => 'Cartera por vendedor',
                        'description' => 'Saldos agrupados por representante de ventas.',
                        'icon' =>
                            'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
                        'route' => route('portfolio.by-rep'),
                    ]
                    : null,
                $canApprove
                    ? [
                        'title' => 'Verificación QR',
                        'description' => 'Comprobantes verificables de forma pública.',
                        'icon' => 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 3h3v3M14 14h3',
                        'route' => route('admin.verification-codes'),
                    ]
                    : null,
            ]),
        );
    @endphp

    <section aria-labelledby="modules-heading">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Operación diaria</p>
                <h2 id="modules-heading" class="mt-1 text-xl font-semibold tracking-tight text-ink">
                    Módulos
                </h2>
            </div>

            <div class="flex items-center gap-3">
                <p class="text-sm text-muted">Accesos disponibles según tu rol.</p>
                <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-xs font-semibold text-muted">
                    {{ count($modules) }}
                </span>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($modules as $module)
                @php
                    $isAvailable = isset($module['route']);
                    $tag = $isAvailable ? 'a' : 'article';
                @endphp

                <{{ $tag }} wire:key="module-{{ $module['title'] }}"
                    @if ($isAvailable) href="{{ $module['route'] }}"
                        wire:navigate @endif
                    class="group relative flex min-h-[200px] flex-col overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                    <div
                        class="pointer-events-none absolute -right-8 -top-8 size-28 rounded-full bg-brand-500/5 transition group-hover:bg-brand-500/10">
                    </div>

                    <div class="relative flex items-start justify-between gap-3">
                        <div
                            class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-500 group-hover:text-ink">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="{{ $module['icon'] }}" />
                            </svg>
                        </div>

                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $isAvailable ? 'bg-brand-50 text-brand-700' : 'bg-black/[0.04] text-muted' }}">
                            <span
                                class="size-1.5 rounded-full {{ $isAvailable ? 'bg-brand-500' : 'bg-black/30' }}"></span>
                            {{ $isAvailable ? 'Disponible' : 'Próximamente' }}
                        </span>
                    </div>

                    <div class="relative mt-auto pt-6">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-ink transition group-hover:text-brand-700">
                                {{ $module['title'] }}
                            </h3>

                            @if ($isAvailable)
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-muted transition group-hover:translate-x-0.5 group-hover:bg-brand-50 group-hover:text-brand-700">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M5 12h14m-6-6 6 6-6 6" />
                                    </svg>
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 text-sm leading-6 text-muted">
                            {{ $module['description'] }}
                        </p>
                    </div>
                    </{{ $tag }}>
            @endforeach
        </div>
    </section>
</div>
