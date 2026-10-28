<x-guest-layout title="Verificación en dos pasos">
    <div class="mb-6">
        <p class="eyebrow">Seguridad</p>
        <h1 class="page-title">Verificación en dos pasos</h1>
        <p class="page-subtitle">Ingresa el código de tu aplicación autenticadora o un código de recuperación.</p>
    </div>

    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
            @csrf

            <div x-show="! recovery">
                <label for="code" class="label">Código de verificación</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="input text-center text-lg tracking-[0.3em]">
                @error('code')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="recovery" x-cloak>
                <label for="recovery_code" class="label">Código de recuperación</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                    class="input">
                @error('recovery_code')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full">
                Verificar
            </button>
        </form>

        <button type="button" x-on:click="recovery = ! recovery" class="mt-5 w-full text-center text-sm font-semibold text-brand-700 hover:text-brand-800">
            <span x-show="! recovery">Usar un código de recuperación</span>
            <span x-show="recovery" x-cloak>Usar el código de la aplicación</span>
        </button>
    </div>
</x-guest-layout>
