<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="dHermes: gestión de cartera, cobros, arqueos y verificación de comprobantes.">
        <title>{{ config('app.name') }} · Cartera y cobranzas</title>
        <x-pwa-meta />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh bg-canvas text-ink antialiased">
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_12%_0%,rgba(255,103,31,0.16),transparent_32%),radial-gradient(circle_at_90%_20%,rgba(10,10,10,0.05),transparent_28%)]"></div>

        <div class="relative mx-auto flex min-h-dvh max-w-6xl flex-col px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-ink text-xs font-extrabold text-brand-500">dH</span>
                    <span>dHermes</span>
                </a>

                <div class="flex items-center gap-2">
                    <button type="button" data-pwa-install hidden
                        class="hidden rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 sm:inline-flex">
                        Instalar app
                    </button>
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                        class="rounded-lg bg-ink px-4 py-2 text-sm font-semibold text-white transition hover:bg-black/80">
                        {{ auth()->check() ? 'Ir al inicio' : 'Iniciar sesión' }}
                    </a>
                </div>
            </header>

            <main class="flex flex-1 items-center py-14 sm:py-20">
                <div class="grid w-full items-center gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-brand-700 shadow-sm">
                            <span class="size-1.5 rounded-full bg-brand-500"></span>
                            Control de cobranzas
                        </span>

                        <h1 class="mt-6 max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-ink sm:text-5xl lg:text-6xl">
                            Tu cartera, tus cobros y tus arqueos en un solo lugar.
                        </h1>

                        <p class="mt-6 max-w-xl text-base leading-7 text-black/60 sm:text-lg">
                            dHermes centraliza la información de cartera proveniente de SAP Business One y acompaña el trabajo diario de administradores, supervisores, vendedores y cobradores.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                                class="inline-flex min-h-12 items-center justify-center rounded-xl bg-brand-500 px-5 py-3 text-sm font-bold text-ink transition hover:bg-brand-400 focus:outline-none focus:ring-4 focus:ring-brand-500/25">
                                {{ auth()->check() ? 'Continuar al panel' : 'Acceder al sistema' }}
                            </a>
                            <button type="button" data-pwa-install hidden
                                class="inline-flex min-h-12 items-center justify-center rounded-xl border border-black/10 bg-white px-5 py-3 text-sm font-semibold text-ink transition hover:bg-black/[0.03]">
                                Instalar en este dispositivo
                            </button>
                        </div>

                        <p data-pwa-ios-instructions hidden class="mt-3 max-w-md text-sm leading-6 text-black/60">
                            En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.
                        </p>

                        <dl class="mt-12 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-black/10 bg-white/80 p-4 shadow-sm">
                                <dt class="text-sm font-semibold text-ink">Cartera</dt>
                                <dd class="mt-1 text-sm leading-6 text-black/60">Saldos por cliente, vendedor y moneda.</dd>
                            </div>
                            <div class="rounded-2xl border border-black/10 bg-white/80 p-4 shadow-sm">
                                <dt class="text-sm font-semibold text-ink">Cobranzas</dt>
                                <dd class="mt-1 text-sm leading-6 text-black/60">Registro y conciliación con documentos SAP.</dd>
                            </div>
                            <div class="rounded-2xl border border-black/10 bg-white/80 p-4 shadow-sm">
                                <dt class="text-sm font-semibold text-ink">Trazabilidad</dt>
                                <dd class="mt-1 text-sm leading-6 text-black/60">Auditoría de cada operación y verificación QR.</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                        <div class="absolute -inset-4 rounded-[2rem] bg-brand-500/10 blur-2xl" aria-hidden="true"></div>

                        <div class="relative overflow-hidden rounded-3xl border border-black/10 bg-white p-5 shadow-2xl shadow-black/10 sm:p-6">
                            <div class="flex items-center justify-between border-b border-black/5 pb-4">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-brand-500"></span>
                                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-black/60">Panel dHermes</span>
                                </div>
                                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-700">En línea</span>
                            </div>

                            <div class="mt-5 rounded-2xl bg-ink p-5 text-white">
                                <p class="text-xs font-medium text-white/50">Cartera pendiente</p>
                                <p class="mt-2 text-3xl font-semibold tracking-tight">$ 1.284.500</p>
                                <div class="mt-4 flex items-center justify-between text-xs">
                                    <span class="rounded-full bg-brand-500 px-2.5 py-1 font-bold text-ink">+8,4% este mes</span>
                                    <span class="text-white/40">Actualizado hoy</span>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl border border-black/10 bg-canvas p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-black/60">Cobrado</p>
                                    <p class="mt-2 text-xl font-semibold">$ 486.200</p>
                                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-black/10">
                                        <div class="h-full w-2/3 rounded-full bg-brand-500"></div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-black/10 bg-canvas p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-black/60">Documentos</p>
                                    <p class="mt-2 text-xl font-semibold">128</p>
                                    <p class="mt-3 text-xs text-black/60">94 conciliados</p>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-3 rounded-2xl border border-black/10 p-4">
                                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-500 text-xs font-extrabold text-ink">dH</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">Arqueo de caja</p>
                                    <p class="text-xs text-black/60">Cerrado sin diferencias</p>
                                </div>
                                <svg class="ml-auto size-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="flex flex-col gap-2 border-t border-black/10 py-6 text-xs text-black/60 sm:flex-row sm:items-center sm:justify-between">
                <p>dHermes · Gestión de cartera y cobranzas</p>
                <p>Disponible como aplicación instalable.</p>
            </footer>
        </div>
    </body>
</html>
