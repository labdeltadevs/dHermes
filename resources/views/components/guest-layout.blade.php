@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="dHermes: gestión de cartera, cobros, arqueos y verificación de comprobantes.">
        <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
        <x-pwa-meta />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh bg-canvas text-ink antialiased">
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(255,103,31,0.12),transparent_38%)]"></div>

        <main class="relative flex min-h-dvh flex-col px-4 py-6 sm:px-6 sm:py-10">
            <header class="mx-auto flex w-full max-w-5xl items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-ink text-xs font-extrabold text-brand-500">dH</span>
                    <span>dHermes</span>
                </a>

                <a href="{{ route('home') }}" class="text-sm font-medium text-black/60 transition hover:text-ink">
                    Volver al inicio
                </a>
            </header>

            <div class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-md">
                    <div class="rounded-2xl border border-black/10 bg-white p-6 shadow-xl shadow-black/[0.04] sm:p-8">
                        {{ $slot }}
                    </div>

                    <div class="mt-5 flex flex-col items-center gap-3 text-center">
                        <button type="button" data-pwa-install hidden
                            class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-100">
                            Instalar aplicación
                        </button>
                        <p data-pwa-ios-instructions hidden class="max-w-xs text-xs leading-5 text-black/60">
                            En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.
                        </p>
                        <p class="text-xs text-black/60">Acceso restringido a personal autorizado.</p>
                    </div>
                </div>
            </div>
        </main>

        @livewireScripts
    </body>
</html>
