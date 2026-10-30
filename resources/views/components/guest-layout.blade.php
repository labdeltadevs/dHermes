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
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(255,106,36,0.16),transparent_42%)]"></div>
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_bottom_right,rgba(16,185,129,0.12),transparent_40%)]"></div>

        <main class="relative flex min-h-dvh flex-col px-4 py-6 sm:px-6 sm:py-10 safe-top">
            <header class="mx-auto flex w-full max-w-5xl items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold tracking-tight text-ink">
                    <x-brand-mark class="size-9 text-brand-600" />
                    <span>dHermes</span>
                </a>

                <a href="{{ route('home') }}" class="chip transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-800">
                    Volver al inicio
                </a>
            </header>

            <div class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-md">
                    <div class="card p-6 sm:p-8">
                        {{ $slot }}
                    </div>

                    <div class="mt-5 flex flex-col items-center gap-3 text-center">
                        <button type="button" data-pwa-install hidden class="btn btn-outline btn-sm">
                            Instalar aplicación
                        </button>
                        <p data-pwa-ios-instructions hidden class="max-w-xs text-xs leading-5 text-muted">
                            En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.
                        </p>
                        <p class="text-xs text-muted">Acceso restringido a personal autorizado.</p>
                    </div>
                </div>
            </div>
        </main>

        @livewireScripts
    </body>
</html>
