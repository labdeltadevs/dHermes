<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Mi perfil')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $twoFactorCode = '';

    public bool $showRecoveryCodes = false;

    public bool $confirmingLogout = false;

    public string $logoutPassword = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfileInformation(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
        ])->save();

        session()->flash('status', 'Perfil actualizado.');
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults() ?? Password::default(), 'confirmed'],
        ]);

        $user = auth()->user();

        if (! Hash::check($validated['currentPassword'], $user->password_hash)) {
            $this->addError('currentPassword', 'La contraseña actual no coincide.');

            return;
        }

        $user->forceFill([
            'password_hash' => Hash::make($validated['password']),
        ])->save();

        session()->put(['password_hash_web' => $user->getAuthPassword()]);

        $this->reset('currentPassword', 'password', 'password_confirmation');
        session()->flash('status', 'Contraseña actualizada.');
    }

    public function enableTwoFactorAuthentication(): void
    {
        if (! $this->confirmCurrentPassword()) {
            return;
        }

        app(EnableTwoFactorAuthentication::class)(auth()->user());

        $this->reset('currentPassword', 'twoFactorCode');
        $this->showRecoveryCodes = false;
        session()->flash('status', 'Escanea el código QR y confirma la verificación en dos pasos.');
    }

    public function confirmTwoFactorAuthentication(): void
    {
        $this->validate([
            'twoFactorCode' => ['required', 'string'],
        ]);

        try {
            app(ConfirmTwoFactorAuthentication::class)(auth()->user(), $this->twoFactorCode);
        } catch (ValidationException $exception) {
            $this->addError('twoFactorCode', $exception->validator->errors()->first('code'));

            return;
        }

        $this->reset('twoFactorCode');
        session()->flash('status', 'Verificación en dos pasos activada.');
    }

    public function disableTwoFactorAuthentication(): void
    {
        if (! $this->confirmCurrentPassword()) {
            return;
        }

        app(DisableTwoFactorAuthentication::class)(auth()->user());

        $this->reset('currentPassword', 'twoFactorCode');
        $this->showRecoveryCodes = false;
        session()->flash('status', 'Verificación en dos pasos desactivada.');
    }

    public function regenerateRecoveryCodes(): void
    {
        if (! $this->confirmCurrentPassword()) {
            return;
        }

        app(GenerateNewRecoveryCodes::class)(auth()->user());

        $this->reset('currentPassword');
        $this->showRecoveryCodes = true;
        session()->flash('status', 'Se generaron nuevos códigos de recuperación.');
    }

    public function logoutOtherBrowserSessions(): void
    {
        $this->validate([
            'logoutPassword' => ['required', 'string'],
        ]);

        $user = auth()->user();

        if (! Hash::check($this->logoutPassword, $user->password_hash)) {
            $this->addError('logoutPassword', 'La contraseña no coincide.');

            return;
        }

        Auth::guard('web')->logoutOtherDevices($this->logoutPassword);

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', request()->session()->getId())
                ->delete();
        }

        request()->session()->put([
            'password_hash_web' => $user->fresh()->getAuthPassword(),
        ]);

        $this->reset('logoutPassword', 'confirmingLogout');
        session()->flash('status', 'Se cerraron las otras sesiones.');
    }

    public function render()
    {
        $user = auth()->user()->fresh();

        return $this->view()->with([
            'user' => $user,
            'qrCode' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null
                ? $user->twoFactorQrCodeSvg()
                : null,
            'secretKey' => $user->two_factor_secret !== null ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $this->showRecoveryCodes ? $user->recoveryCodes() : [],
            'sessions' => $this->sessions($user->id),
        ]);
    }

    private function confirmCurrentPassword(): bool
    {
        $this->validate([
            'currentPassword' => ['required', 'string'],
        ]);

        if (! Hash::check($this->currentPassword, auth()->user()->password_hash)) {
            $this->addError('currentPassword', 'La contraseña actual no coincide.');

            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, object>
     */
    private function sessions(string $userId): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): object => (object) [
                'browser' => $this->browserLabel($session->user_agent),
                'ip_address' => $session->ip_address,
                'is_current' => $session->id === request()->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ]);
    }

    private function browserLabel(?string $userAgent): string
    {
        $agent = $userAgent ?? '';

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Microsoft Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Dispositivo desconocido',
        };

        return $browser.' · '.$platform;
    }
};
?>

<div class="space-y-5 sm:space-y-6">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Cuenta</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Mi perfil</h1>
        <p class="mt-1.5 text-sm text-black/60">Administra tus datos, seguridad y sesiones activas.</p>
    </div>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="max-w-2xl">
            <h2 class="text-base font-semibold text-ink">Información personal</h2>
            <p class="mt-1 text-sm text-black/60">Actualiza el nombre y correo con los que accedes.</p>

            <form wire:submit="updateProfileInformation" class="mt-5 space-y-5">
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-black/70">Nombre</label>
                    <input id="name" type="text" wire:model="name" required autocomplete="name"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-black/70">Correo electrónico</label>
                    <input id="email" type="email" wire:model="email" required autocomplete="email"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                    @error('email')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition sm:w-auto hover:bg-black/80">
                    Guardar cambios
                </button>
            </form>
        </div>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="max-w-2xl">
            <h2 class="text-base font-semibold text-ink">Contraseña</h2>
            <p class="mt-1 text-sm text-black/60">Usa una contraseña larga y única.</p>

            <form wire:submit="updatePassword" class="mt-5 space-y-5">
                <div>
                    <label for="current-password" class="mb-1.5 block text-sm font-medium text-black/70">Contraseña actual</label>
                    <input id="current-password" type="password" wire:model="currentPassword" required autocomplete="current-password"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                    @error('currentPassword')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="new-password" class="mb-1.5 block text-sm font-medium text-black/70">Nueva contraseña</label>
                        <input id="new-password" type="password" wire:model="password" required autocomplete="new-password"
                            class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                        @error('password')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password-confirmation" class="mb-1.5 block text-sm font-medium text-black/70">Confirmar contraseña</label>
                        <input id="password-confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password"
                            class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                    </div>
                </div>

                <button type="submit" class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition sm:w-auto hover:bg-black/80">
                    Actualizar contraseña
                </button>
            </form>
        </div>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="max-w-2xl">
            <h2 class="text-base font-semibold text-ink">Verificación en dos pasos</h2>
            <p class="mt-1 text-sm text-black/60">Protege tu cuenta con una aplicación autenticadora.</p>

            @if ($user->hasEnabledTwoFactorAuthentication())
                <div class="mt-5 rounded-xl border border-brand-200 bg-brand-50 p-4">
                    <p class="text-sm font-medium text-brand-900">La verificación en dos pasos está activa.</p>
                </div>

                @if ($showRecoveryCodes)
                    <div class="mt-4 rounded-xl border border-black/10 bg-canvas p-4">
                        <p class="text-sm font-medium text-ink">Códigos de recuperación</p>
                        <div class="mt-3 grid gap-2 font-mono text-sm text-black/70 sm:grid-cols-2">
                            @foreach ($recoveryCodes as $code)
                                <span wire:key="recovery-{{ $code }}" class="rounded-lg bg-white px-3 py-2">{{ $code }}</span>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-black/60">Guárdalos en un lugar seguro. Cada código se puede usar una sola vez.</p>
                    </div>
                @else
                    <button type="button" wire:click="$set('showRecoveryCodes', true)" class="mt-4 text-sm font-medium text-brand-700 hover:text-brand-800">
                        Mostrar códigos de recuperación
                    </button>
                @endif

                <div class="mt-5">
                    <label for="two-factor-disable-password" class="mb-1.5 block text-sm font-medium text-black/70">Contraseña actual</label>
                    <input id="two-factor-disable-password" type="password" wire:model="currentPassword" autocomplete="current-password"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                    @error('currentPassword')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-3">
                    <button type="button" wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled"
                        class="min-h-11 rounded-xl border border-black/15 px-4 py-2.5 text-sm font-medium text-black/70 transition hover:bg-black/[0.03] disabled:opacity-50">
                        Regenerar códigos
                    </button>
                    <button type="button" wire:click="disableTwoFactorAuthentication" wire:loading.attr="disabled"
                        class="min-h-11 w-full rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition sm:w-auto hover:bg-rose-500 disabled:opacity-50">
                        Desactivar 2FA
                    </button>
                </div>
            @elseif ($user->two_factor_secret !== null)
                <div class="mt-5 space-y-4">
                    <p class="text-sm text-black/60">Escanea el código QR con tu aplicación autenticadora y confirma el código generado.</p>

                    <div class="flex justify-center rounded-xl border border-black/10 bg-white p-4">
                        <div class="size-44 [&>svg]:size-full sm:size-48">{!! $qrCode !!}</div>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-black/60">Clave secreta</p>
                        <p class="mt-1 break-all rounded-lg bg-black/[0.04] px-3 py-2 font-mono text-sm text-ink">{{ $secretKey }}</p>
                    </div>

                    <div>
                        <label for="two-factor-code" class="mb-1.5 block text-sm font-medium text-black/70">Código de confirmación</label>
                        <input id="two-factor-code" type="text" wire:model="twoFactorCode" inputmode="numeric" autocomplete="one-time-code"
                            class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-center text-lg tracking-[0.3em] text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                        @error('twoFactorCode')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="button" wire:click="confirmTwoFactorAuthentication" wire:loading.attr="disabled"
                        class="min-h-11 w-full rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition sm:w-auto hover:bg-brand-400 disabled:opacity-50">
                        Confirmar y activar
                    </button>
                </div>
            @else
                <div class="mt-5">
                    <label for="two-factor-password" class="mb-1.5 block text-sm font-medium text-black/70">Contraseña actual</label>
                    <input id="two-factor-password" type="password" wire:model="currentPassword" autocomplete="current-password"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                    @error('currentPassword')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    <button type="button" wire:click="enableTwoFactorAuthentication" wire:loading.attr="disabled"
                        class="mt-4 min-h-11 w-full rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition sm:w-auto hover:bg-brand-400 disabled:opacity-50">
                        Activar verificación en dos pasos
                    </button>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="max-w-2xl">
            <h2 class="text-base font-semibold text-ink">Sesiones del navegador</h2>
            <p class="mt-1 text-sm text-black/60">Si reconoces todas las sesiones, puedes cerrar las demás.</p>

            <div class="mt-5 space-y-3">
                @forelse ($sessions as $session)
                    <div wire:key="session-{{ $loop->index }}" class="flex flex-col gap-2 rounded-xl border border-black/10 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-ink">
                                {{ $session->browser }}
                                @if ($session->is_current)
                                    <span class="ml-2 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">Esta sesión</span>
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-black/60">{{ $session->ip_address ?? 'IP desconocida' }} · {{ $session->last_active }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-black/60">No hay sesiones registradas.</p>
                @endforelse
            </div>

            @if ($sessions->count() > 1)
                @if ($confirmingLogout)
                    <div class="mt-5 rounded-xl border border-black/10 bg-canvas p-4">
                        <label for="logout-password" class="mb-1.5 block text-sm font-medium text-black/70">Confirma tu contraseña</label>
                        <input id="logout-password" type="password" wire:model="logoutPassword" autocomplete="current-password"
                            class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink sm:text-sm outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15">
                        @error('logoutPassword')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:gap-3">
                            <button type="button" wire:click="logoutOtherBrowserSessions" wire:loading.attr="disabled"
                                class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition sm:w-auto hover:bg-black/80 disabled:opacity-50">
                                Cerrar otras sesiones
                            </button>
                            <button type="button" wire:click="$set('confirmingLogout', false)"
                                class="min-h-11 rounded-xl border border-black/15 px-4 py-2.5 text-sm font-medium text-black/70 transition hover:bg-white">
                                Cancelar
                            </button>
                        </div>
                    </div>
                @else
                    <button type="button" wire:click="$set('confirmingLogout', true)"
                        class="mt-5 min-h-11 rounded-xl border border-black/15 px-4 py-2.5 text-sm font-medium text-black/70 transition hover:bg-black/[0.03]">
                        Cerrar otras sesiones
                    </button>
                @endif
            @endif
        </div>
    </section>
</div>
