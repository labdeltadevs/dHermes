<x-guest-layout title="Nueva contraseña">
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Seguridad</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Nueva contraseña</h1>
        <p class="mt-1.5 text-sm text-black/60">Define una contraseña larga y única para tu cuenta.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-black/70">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="username"
                class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-black/70">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            @error('password')
                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-black/70">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
        </div>

        <button type="submit"
            class="flex min-h-11 w-full items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-brand-400 focus:outline-none focus:ring-4 focus:ring-brand-500/25">
            Guardar contraseña
        </button>
    </form>
</x-guest-layout>
