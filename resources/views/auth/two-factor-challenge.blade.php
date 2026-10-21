<x-guest-layout title="Verificación en dos pasos">
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Seguridad</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Verificación en dos pasos</h1>
        <p class="mt-1.5 text-sm leading-6 text-black/60">Ingresa el código de tu aplicación autenticadora o un código de recuperación.</p>
    </div>

    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
            @csrf

            <div x-show="! recovery">
                <label for="code" class="mb-1.5 block text-sm font-medium text-black/70">Código de verificación</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-center text-lg tracking-[0.3em] text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                @error('code')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="mb-1.5 block text-sm font-medium text-black/70">Código de recuperación</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                @error('recovery_code')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="flex min-h-11 w-full items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-brand-400 focus:outline-none focus:ring-4 focus:ring-brand-500/25">
                Verificar
            </button>
        </form>

        <button type="button" x-on:click="recovery = ! recovery" class="mt-5 w-full text-center text-sm font-semibold text-brand-700 hover:text-brand-800">
            <span x-show="! recovery">Usar un código de recuperación</span>
            <span x-show="recovery" x-cloak>Usar el código de la aplicación</span>
        </button>
    </div>
</x-guest-layout>
