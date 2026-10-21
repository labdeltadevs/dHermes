<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="dHermes: gestión de cartera, cobros, arqueos y verificación de comprobantes.">
        <title>{{ $title ?? config('app.name') }}</title>
        <x-pwa-meta />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh bg-canvas text-ink antialiased">
        @php
            $user = auth()->user();
            $memberships = $user?->activeMemberships() ?? collect();
            $membership = $memberships->firstWhere('tenant_id', $user?->currentTenantId()) ?? $memberships->first();
            $canManageTenant = $membership?->role->canManageTenant() ?? false;
            $canApprove = $membership?->role->canApprove() ?? false;
        @endphp

        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-ink focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
            Saltar al contenido
        </a>

        <div x-data="{ accountOpen: false }" x-on:keydown.escape.window="accountOpen = false" class="min-h-dvh">
            <header class="sticky top-0 z-30 border-b border-black/5 bg-white/95 backdrop-blur">
                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-7">
                        <a href="{{ route('dashboard') }}" wire:navigate class="flex shrink-0 items-center gap-2.5 font-semibold tracking-tight">
                            <span class="flex size-9 items-center justify-center rounded-xl bg-ink text-xs font-extrabold text-brand-500">dH</span>
                            <span class="hidden text-[15px] sm:inline">dHermes</span>
                        </a>

                        <nav class="hidden items-center gap-1 md:flex" aria-label="Navegación principal">
                            <a href="{{ route('dashboard') }}" wire:navigate wire:current="bg-brand-50 text-brand-700"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                Inicio
                            </a>
                            <a href="{{ route('portfolio') }}" wire:navigate wire:current="bg-brand-50 text-brand-700"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                Cartera
                            </a>
                            <a href="{{ route('receipts') }}" wire:navigate wire:current="bg-brand-50 text-brand-700"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                Cobros
                            </a>
                            <a href="{{ route('collections.index') }}" wire:navigate wire:current="bg-brand-50 text-brand-700"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                Cobranzas
                            </a>
                            <a href="{{ route('cash-sessions.index') }}" wire:navigate wire:current="bg-brand-50 text-brand-700"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                Arqueos
                            </a>
                            @if ($canManageTenant)
                                <a href="{{ route('admin.users') }}" wire:navigate wire:current="bg-brand-50 text-brand-700"
                                    class="rounded-lg px-3 py-2 text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                    Usuarios
                                </a>
                            @endif
                        </nav>
                    </div>

                    <div class="hidden items-center gap-3 md:flex">
                        <button type="button" data-pwa-install hidden
                            class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">
                            Instalar app
                        </button>

                        <div class="relative" x-on:click.outside="accountOpen = false">
                            <button type="button" x-on:click="accountOpen = ! accountOpen" :aria-expanded="accountOpen"
                                class="flex items-center gap-2.5 rounded-xl border border-black/10 bg-white p-1.5 pr-3 text-left transition hover:bg-black/[0.03]">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-ink text-xs font-semibold text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block max-w-40 truncate text-sm font-medium">{{ auth()->user()->name }}</span>
                                    <span class="block text-xs text-black/60">{{ $membership?->role->label() }}</span>
                                </span>
                                <svg class="size-4 text-black/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>

                            <div x-show="accountOpen" x-cloak x-transition.origin.top.right
                                class="absolute right-0 mt-2 w-72 origin-top-right rounded-2xl border border-black/10 bg-white p-2 shadow-xl shadow-black/10">
                                <div class="border-b border-black/5 px-3 py-2.5">
                                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-black/60">{{ auth()->user()->email }}</p>
                                    <p class="mt-1 text-xs font-medium text-brand-700">{{ $membership?->role->label() }}</p>
                                </div>

                                <x-tenant-switcher :memberships="$memberships" :current-tenant-id="$membership?->tenant_id" />

                                <a href="{{ route('profile') }}" wire:navigate
                                    class="mt-1 flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-ink transition hover:bg-brand-50">
                                    <svg class="size-4 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" />
                                    </svg>
                                    Mi perfil
                                </a>

                                <button type="button" data-pwa-install hidden
                                    class="mt-1 flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-ink transition hover:bg-brand-50">
                                    <svg class="size-4 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                                    </svg>
                                    Instalar aplicación
                                </button>

                                <p data-pwa-ios-instructions hidden class="mx-2 mt-1 rounded-lg bg-brand-50 px-3 py-2 text-xs leading-5 text-brand-800">
                                    En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.
                                </p>

                                <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-black/5 pt-1">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-black/60 transition hover:bg-black/[0.04] hover:text-ink">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12H3m0 0 4-4m-4 4 4 4m4-11h5a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5" />
                                        </svg>
                                        Cerrar sesión
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <button type="button" x-on:click="accountOpen = true"
                        class="flex items-center gap-2 rounded-xl border border-black/10 bg-white p-1.5 pr-2.5 md:hidden"
                        aria-label="Abrir menú de cuenta">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-ink text-xs font-semibold text-white">
                            {{ auth()->user()->initials() }}
                        </span>
                        <svg class="size-4 text-black/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>
                </div>

                <div data-pwa-connection hidden class="border-t border-brand-200 bg-brand-50 px-4 py-2 text-center text-xs font-medium text-brand-800">
                    Sin conexión. Algunas acciones no estarán disponibles hasta recuperar la red.
                </div>
            </header>

            <main id="contenido" class="mx-auto max-w-7xl px-4 pb-28 pt-6 sm:px-6 sm:pt-8 md:pb-10 lg:px-8">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>

            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-black/10 bg-white/95 backdrop-blur md:hidden" aria-label="Navegación móvil">
                <div class="mx-auto flex max-w-lg items-stretch px-2 safe-bottom">
                    <a href="{{ route('dashboard') }}" wire:navigate wire:current="text-brand-700"
                        class="group flex min-h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-medium text-black/60 transition active:bg-black/[0.03]">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z" />
                        </svg>
                        Inicio
                    </a>

                    <a href="{{ route('portfolio') }}" wire:navigate wire:current="text-brand-700"
                        class="group flex min-h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-medium text-black/60 transition active:bg-black/[0.03]">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h10M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        </svg>
                        Cartera
                    </a>

                    <a href="{{ route('collections.index') }}" wire:navigate wire:current="text-brand-700"
                        class="group flex min-h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-medium text-black/60 transition active:bg-black/[0.03]">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m-4-9h8M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" />
                        </svg>
                        Cobranzas
                    </a>

                    <a href="{{ route('cash-sessions.index') }}" wire:navigate wire:current="text-brand-700"
                        class="group flex min-h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-medium text-black/60 transition active:bg-black/[0.03]">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h10M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                        </svg>
                        Arqueos
                    </a>

                    <a href="{{ route('profile') }}" wire:navigate wire:current="text-brand-700"
                        class="group flex min-h-16 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[11px] font-medium text-black/60 transition active:bg-black/[0.03]">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" />
                        </svg>
                        Perfil
                    </a>
                </div>
            </nav>

            <div x-show="accountOpen" x-cloak class="fixed inset-0 z-40 md:hidden">
                <div x-show="accountOpen" x-transition.opacity class="absolute inset-0 bg-black/35" x-on:click="accountOpen = false"></div>

                <div x-show="accountOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                    role="dialog" aria-modal="true" aria-label="Menú de cuenta"
                    class="absolute inset-x-0 bottom-0 rounded-t-3xl border-t border-black/10 bg-white p-5 pb-8 shadow-2xl safe-bottom">
                    <div class="mx-auto mb-5 h-1 w-10 rounded-full bg-black/10"></div>

                    <div class="flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-ink text-sm font-semibold text-white">
                            {{ auth()->user()->initials() }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-black/60">{{ auth()->user()->email }}</p>
                        </div>
                        <span class="ml-auto rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">{{ $membership?->role->label() }}</span>
                    </div>

                    <x-tenant-switcher :memberships="$memberships" :current-tenant-id="$membership?->tenant_id" />

                    <div class="mt-5 space-y-2">
                        <a href="{{ route('profile') }}" wire:navigate
                            class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-medium text-ink transition hover:bg-black/[0.04]">
                            <svg class="size-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" />
                            </svg>
                            Mi perfil
                        </a>

                        @if ($canManageTenant)
                            <a href="{{ route('admin.users') }}" wire:navigate
                                class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-medium text-ink transition hover:bg-black/[0.04]">
                                <svg class="size-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                Usuarios
                            </a>
                        @endif

                        @if ($canApprove)
                            <a href="{{ route('admin.verification-codes') }}" wire:navigate
                                class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-medium text-ink transition hover:bg-black/[0.04]">
                                <svg class="size-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 3h3v3M14 14h3" />
                                </svg>
                                Comprobantes
                            </a>
                        @endif

                        <button type="button" data-pwa-install hidden
                            class="flex w-full items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-left text-sm font-semibold text-brand-800">
                            <svg class="size-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                            </svg>
                            Instalar aplicación
                        </button>

                        <p data-pwa-ios-instructions hidden class="rounded-xl bg-brand-50 px-4 py-3 text-xs leading-5 text-brand-800">
                            En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.
                        </p>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-medium text-black/60 transition hover:bg-black/[0.04]">
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12H3m0 0 4-4m-4 4 4 4m4-11h5a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-5" />
                                </svg>
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div data-pwa-update-banner hidden
                class="fixed inset-x-4 bottom-20 z-40 rounded-2xl border border-black/10 bg-ink p-4 text-white shadow-2xl md:bottom-6 md:left-auto md:right-6 md:w-96">
                <p class="text-sm font-semibold">Hay una nueva versión de dHermes.</p>
                <p class="mt-1 text-xs leading-5 text-white/60">Actualiza para cargar las últimas mejoras.</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" data-pwa-update class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-bold text-ink transition hover:bg-brand-400">
                        Actualizar ahora
                    </button>
                    <button type="button" data-pwa-dismiss-update class="rounded-lg border border-white/15 px-3 py-2 text-xs font-medium text-white/70 transition hover:bg-white/10">
                        Después
                    </button>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
