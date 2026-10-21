<x-guest-layout title="Iniciar sesión">
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Bienvenido</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Iniciar sesión</h1>
        <p class="mt-1.5 text-sm text-black/60">Ingresa con tu cuenta de dHermes.</p>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-black/70">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            @error('email')
                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between gap-4">
                <label for="password" class="block text-sm font-medium text-black/70">Contraseña</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">¿La olvidaste?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            @error('password')
                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 text-sm text-black/60">
            <input type="checkbox" name="remember" class="size-4 rounded border-black/20 text-brand-700 focus:ring-brand-500">
            Recordarme
        </label>

        <button type="submit"
            class="flex min-h-11 w-full items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-brand-400 focus:outline-none focus:ring-4 focus:ring-brand-500/25">
            Entrar
        </button>
    </form>
</x-guest-layout>
