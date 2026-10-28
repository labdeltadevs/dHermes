@props([
    'editingTenantId' => null,
    'code' => '',
    'name' => '',
    'taxId' => '',
    'timezone' => 'America/La_Paz',
    'localCurrency' => 'BOB',
    'active' => true,
    'adminName' => '',
    'adminEmail' => '',
    'adminPassword' => '',
    'timezones' => [],
])

<div x-data="{
    open: @entangle('showForm'),
    adminSection: @entangle('adminEmail') !== '' ? 'new' : 'existing',
    close() {
        this.open = false;
        this.$nextTick(() => { $wire.cancel(); });
    }
}" x-show="open" x-cloak x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
    @keydown.window.escape="close()">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-ink/60 backdrop-blur-sm" @click="close()"></div>

    {{-- Panel --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
        class="relative w-full max-w-3xl flex flex-col max-h-[95vh] sm:max-h-[90vh] overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
            <div class="flex items-start gap-3">
                <div
                    class="hidden sm:flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M16.5 6.75h.75m-.75 3h.75m-.75 3h.75" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-ink">
                        {{ $editingTenantId ? 'Editar empresa' : 'Nueva empresa' }}
                    </h2>
                    <p class="mt-0.5 text-sm text-muted">
                        {{ $editingTenantId
                            ? 'Actualiza los datos generales de la empresa.'
                            : 'Define los datos de la empresa y su administrador inicial.' }}
                    </p>
                </div>
            </div>
            <button type="button" @click="close()"
                class="-mr-1.5 -mt-1 shrink-0 rounded-lg p-1.5 text-muted transition-colors hover:bg-canvas hover:text-ink">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Form --}}
        <form wire:submit="save" class="flex-1 overflow-y-auto">

            <div class="space-y-6 px-5 py-5 sm:px-6 sm:py-6">

                {{-- Section: Datos generales --}}
                <div>
                    <h3 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 4.5V3.75m0 4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        Datos generales
                    </h3>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        {{-- Código --}}
                        <div>
                            <label for="tenant-code" class="label">Código</label>
                            @if ($editingTenantId)
                                <input id="tenant-code" type="text" value="{{ $code }}" disabled
                                    class="input cursor-not-allowed bg-canvas font-mono uppercase text-muted">
                                <p class="mt-1.5 flex items-center gap-1 text-xs text-muted">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                    El código no se puede modificar
                                </p>
                            @else
                                <div class="relative">
                                    <input id="tenant-code" type="text" wire:model="code" maxlength="20"
                                        class="input pl-3 font-mono uppercase tracking-wide {{ $errors->has('code') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                                </div>
                                <p class="mt-1.5 text-xs text-muted">2-20 caracteres. Solo mayúsculas, números y guiones
                                    bajos.</p>
                                @error('code')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>

                        {{-- Moneda --}}
                        <div>
                            <label for="tenant-currency" class="label">Moneda local</label>
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted">ISO</span>
                                <input id="tenant-currency" type="text" wire:model="localCurrency" maxlength="3"
                                    class="input pl-12 uppercase tracking-widest {{ $errors->has('localCurrency') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                            </div>
                            <p class="mt-1.5 text-xs text-muted">Código de 3 letras (ej. BOB, USD, EUR).</p>
                            @error('localCurrency')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Nombre --}}
                        <div class="sm:col-span-2">
                            <label for="tenant-name" class="label">Nombre</label>
                            <input id="tenant-name" type="text" wire:model="name" required
                                class="input {{ $errors->has('name') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                            @error('name')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- NIT --}}
                        <div class="sm:col-span-2">
                            <label for="tenant-tax-id" class="label">NIT</label>
                            <input id="tenant-tax-id" type="text" wire:model="taxId"
                                class="input {{ $errors->has('taxId') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                            @error('taxId')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Zona horaria --}}
                        <div class="sm:col-span-2">
                            <label for="tenant-timezone" class="label">Zona horaria</label>
                            <select id="tenant-timezone" wire:model="timezone"
                                class="input {{ $errors->has('timezone') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                                @foreach ($timezones as $zone)
                                    <option value="{{ $zone }}">{{ $zone }}</option>
                                @endforeach
                            </select>
                            @error('timezone')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Activo --}}
                        <div class="sm:col-span-2">
                            <label for="tenant-active"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3.5 transition-colors hover:bg-canvas/50">
                                <input id="tenant-active" type="checkbox" wire:model="active"
                                    class="size-5 shrink-0 rounded-md border-line text-brand-600 focus:ring-brand-500 focus:ring-offset-0">
                                <div>
                                    <p class="text-sm font-medium text-ink">Empresa activa</p>
                                    <p class="mt-0.5 text-xs text-muted">Si está inactiva, los usuarios no podrán
                                        acceder.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                @if ($editingTenantId === null)
                    {{-- Separator --}}
                    <div class="border-t border-line"></div>

                    {{-- Section: Administrador --}}
                    <div>
                        <h3 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            Administrador inicial
                        </h3>
                        <p class="mt-2 text-sm text-muted">Si el correo ya existe, se le asignará el rol de
                            administrador sin cambiar su contraseña.</p>

                        <div class="mt-4 grid gap-5 sm:grid-cols-2">
                            {{-- Email --}}
                            <div class="sm:col-span-2">
                                <label for="tenant-admin-email" class="label">Correo electrónico</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    <input id="tenant-admin-email" type="email"
                                        wire:model.live.debounce.400ms="adminEmail" placeholder="admin@empresa.com"
                                        class="input pl-10 {{ $errors->has('adminEmail') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                                    {{-- Loading indicator --}}
                                    <div wire:loading wire:target="adminEmail"
                                        class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg class="h-4 w-4 animate-spin text-muted" fill="none"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="3"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                </div>
                                @error('adminEmail')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Name (conditional) --}}
                            <div>
                                <label for="tenant-admin-name" class="label">Nombre</label>
                                <input id="tenant-admin-name" type="text" wire:model="adminName"
                                    placeholder="Nombre del administrador"
                                    class="input {{ $errors->has('adminName') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                                @error('adminName')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Password (conditional) --}}
                            <div>
                                <label for="tenant-admin-password" class="label">Contraseña temporal</label>
                                <div class="relative">
                                    <input id="tenant-admin-password" type="password" wire:model="adminPassword"
                                        autocomplete="new-password" placeholder="••••••••"
                                        class="input {{ $errors->has('adminPassword') ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : '' }}">
                                </div>
                                @error('adminPassword')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @error('status')
                    <div
                        class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            {{-- Footer (sticky) --}}
            <div
                class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-line bg-canvas/80 px-5 py-4 backdrop-blur-md sm:px-6">
                <button type="button" @click="close()" class="btn btn-ghost">
                    Cancelar
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="btn btn-dark min-w-[8rem] justify-center">
                    <span wire:loading.remove
                        wire:target="save">{{ $editingTenantId ? 'Guardar cambios' : 'Crear empresa' }}</span>
                    <span wire:loading wire:target="save class="flex items-center gap-2"">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Guardando…
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
