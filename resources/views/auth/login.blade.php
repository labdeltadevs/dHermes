<x-guest-layout title="Iniciar sesión">
    <div class="mb-6">
        <p class="eyebrow">Bienvenido</p>
        <h1 class="page-title">Iniciar sesión</h1>
        <p class="page-subtitle">Ingresa con tu cuenta de dHermes.</p>
    </div>

    @if (session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-mint-200 bg-mint-50 px-4 py-3 text-sm text-mint-700">
            <svg class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m5 13 4 4L19 7" />
            </svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="label">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="input" placeholder="nombre@empresa.com">
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between gap-4">
                <label for="password" class="label mb-0">Contraseña</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">¿La olvidaste?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="input" placeholder="••••••••">
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 text-sm text-muted">
            <input type="checkbox" name="remember" class="size-4 rounded border-line text-brand-600 focus:ring-brand-500/30">
            Recordarme
        </label>

        <button type="submit" class="btn btn-primary w-full">
            Entrar
        </button>
    </form>
</x-guest-layout>
