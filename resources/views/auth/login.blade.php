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
            <div x-data="{ showPassword: false }" class="relative">
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    x-bind:type="showPassword ? 'text' : 'password'"
                    class="input pr-11" placeholder="••••••••">

                <button type="button" x-on:click="showPassword = ! showPassword"
                    x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    x-bind:aria-pressed="showPassword ? 'true' : 'false'"
                    title="Mostrar u ocultar contraseña"
                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-black/40 transition hover:text-ink focus:outline-none focus-visible:text-ink focus-visible:ring-4 focus-visible:ring-brand-500/15">
                    <svg x-show="! showPassword" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"
                        viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>

                    <svg x-show="showPassword" x-cloak class="size-5" fill="none" stroke="currentColor"
                        stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
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
