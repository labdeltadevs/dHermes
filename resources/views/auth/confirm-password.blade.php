<x-guest-layout title="Confirmar contraseña">
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Seguridad</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Confirmar contraseña</h1>
        <p class="mt-1.5 text-sm text-black/60">Por seguridad, confirma tu contraseña para continuar.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-black/70">Contraseña</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            @error('password')
                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="flex min-h-11 w-full items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-brand-400 focus:outline-none focus:ring-4 focus:ring-brand-500/25">
            Confirmar
        </button>
    </form>
</x-guest-layout>
