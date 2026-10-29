<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Empresas')] class extends Component {
    use WithPagination;

    public bool $showForm = false;

    public ?string $editingTenantId = null;

    public string $search = '';

    public string $status = '';

    public string $code = '';

    public string $name = '';

    public string $taxId = '';

    public string $timezone = 'America/La_Paz';

    public string $localCurrency = 'BOB';

    public bool $active = true;

    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Tenant::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        Gate::authorize('create', Tenant::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        Gate::authorize('update', $tenant);

        $this->editingTenantId = $tenant->id;
        $this->code = $tenant->code;
        $this->name = $tenant->name;
        $this->taxId = $tenant->tax_id ?? '';
        $this->timezone = $tenant->timezone;
        $this->localCurrency = $tenant->local_currency;
        $this->active = $tenant->active;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        if ($this->editingTenantId !== null) {
            $this->updateTenant();

            return;
        }

        $this->createTenant();
    }

    public function toggleActive(string $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        Gate::authorize('toggleActive', $tenant);

        if ($tenant->active && app(CurrentTenant::class)->id() === $tenant->id) {
            $this->addError('status', 'No puedes desactivar la empresa en la que estás operando. Cambia de empresa primero.');

            return;
        }

        $newState = !$tenant->active;

        $tenant->update(['active' => $newState]);

        app(AuditLogger::class)->record($tenant->id, $newState ? 'tenant.activated' : 'tenant.deactivated', 'tenant', $tenant->id, ['code' => $tenant->code, 'name' => $tenant->name], auth()->user());

        session()->flash('status', $newState ? 'Empresa activada.' : 'Empresa desactivada.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $search = trim($this->search);

        $tenants = Tenant::query()
            ->withCount(['memberships' => fn(Builder $query): Builder => $query->withoutGlobalScope('tenant')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $like = '%' . $search . '%';

                    $query->whereLike('code', $like)->orWhereLike('name', $like)->orWhereLike('tax_id', $like);
                });
            })
            ->when($this->status === 'active', fn(Builder $query): Builder => $query->where('active', true))
            ->when($this->status === 'inactive', fn(Builder $query): Builder => $query->where('active', false))
            ->orderBy('name')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        return $this->view()->with([
            'tenants' => $tenants,
            'stats' => [
                'total' => Tenant::query()->count(),
                'active' => Tenant::query()->where('active', true)->count(),
                'inactive' => Tenant::query()->where('active', false)->count(),
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'currentTenantId' => app(CurrentTenant::class)->id(),
        ]);
    }

    private function createTenant(): void
    {
        Gate::authorize('create', Tenant::class);

        $this->code = Str::upper(trim($this->code));
        $this->localCurrency = Str::upper(trim($this->localCurrency));

        $existingAdmin = $this->existingAdmin();

        $this->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]{2,20}$/', Rule::unique('tenants', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'localCurrency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'active' => ['boolean'],
            'adminName' => [Rule::requiredIf($existingAdmin === null), 'nullable', 'string', 'max:255'],
            'adminEmail' => ['required', 'string', 'email', 'max:255'],
            'adminPassword' => [Rule::requiredIf($existingAdmin === null), 'nullable', 'string', Password::defaults()],
        ]);

        if ($existingAdmin !== null && !$existingAdmin->active) {
            $this->addError('adminEmail', 'El usuario existe pero está inactivo. Actívalo antes de asignarlo.');

            return;
        }

        $tenant = DB::transaction(function () use ($existingAdmin): Tenant {
            $tenant = Tenant::create([
                'code' => $this->code,
                'name' => trim($this->name),
                'tax_id' => trim($this->taxId) !== '' ? trim($this->taxId) : null,
                'timezone' => $this->timezone,
                'local_currency' => $this->localCurrency,
                'active' => $this->active,
            ]);

            $admin =
                $existingAdmin ??
                User::create([
                    'name' => trim($this->adminName),
                    'email' => Str::lower(trim($this->adminEmail)),
                    'password_hash' => Hash::make($this->adminPassword),
                    'active' => true,
                ]);

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'role' => TenantRole::Admin,
                'active' => true,
            ]);

            app(AuditLogger::class)->record(
                $tenant->id,
                'tenant.created',
                'tenant',
                $tenant->id,
                [
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'admin_email' => $admin->email,
                ],
                auth()->user(),
            );

            return $tenant;
        });

        $this->resetForm();
        session()->flash('status', 'Empresa ' . $tenant->name . ' creada.');
    }

    private function updateTenant(): void
    {
        $tenant = Tenant::query()->findOrFail($this->editingTenantId);

        Gate::authorize('update', $tenant);

        $this->localCurrency = Str::upper(trim($this->localCurrency));

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'localCurrency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'active' => ['boolean'],
        ]);

        $tenant->update([
            'name' => trim($this->name),
            'tax_id' => trim($this->taxId) !== '' ? trim($this->taxId) : null,
            'timezone' => $this->timezone,
            'local_currency' => $this->localCurrency,
            'active' => $this->active,
        ]);

        app(AuditLogger::class)->record(
            $tenant->id,
            'tenant.updated',
            'tenant',
            $tenant->id,
            [
                'code' => $tenant->code,
                'name' => $tenant->name,
                'tax_id' => $tenant->tax_id,
                'timezone' => $tenant->timezone,
                'local_currency' => $tenant->local_currency,
                'active' => $tenant->active,
            ],
            auth()->user(),
        );

        $this->resetForm();
        session()->flash('status', 'Empresa actualizada.');
    }

    private function existingAdmin(): ?User
    {
        $email = Str::lower(trim($this->adminEmail));

        return $email === '' ? null : User::query()->where('email', $email)->first();
    }

    private function resetForm(): void
    {
        $this->reset('showForm', 'editingTenantId', 'code', 'name', 'taxId', 'timezone', 'localCurrency', 'active', 'adminName', 'adminEmail', 'adminPassword');

        $this->timezone = 'America/La_Paz';
        $this->localCurrency = 'BOB';
        $this->active = true;
        $this->resetValidation();
    }
};
?>

<div class="space-y-5 sm:space-y-6" x-data="{ alertVisible: true }">
    <!-- ===== Header ===== -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M16.5 6.75h.75m-.75 3h.75m-.75 3h.75" />
                </svg>
            </div>
            <div>
                <x-breadcrumb :items="[
                    ['label' => 'Inicio', 'url' => route('dashboard')],
                    ['label' => 'Administración'],
                    ['label' => 'Empresas'],
                ]" />
                <h1 class="page-title">Empresas</h1>
                <p class="mt-1 text-sm text-muted">Crea y administra los tenants del sistema.</p>
            </div>
        </div>

        <button type="button" wire:click="create" class="btn btn-primary w-full shrink-0 sm:w-auto">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span>Nueva empresa</span>
        </button>
    </div>

    <!-- ===== Stats ===== -->
    <div class="grid gap-4 sm:grid-cols-3">
        {{-- Total --}}
        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1 bg-brand-500"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Total</p>
                    <p class="mt-1.5 text-3xl font-bold tabular-nums text-ink">{{ $stats['total'] }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Activas --}}
        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Activas</p>
                    <p class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ $stats['active'] }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Inactivas --}}
        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1 bg-gray-300"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Inactivas</p>
                    <p class="mt-1.5 text-3xl font-bold tabular-nums text-muted">{{ $stats['inactive'] }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Flash error ===== -->
    @if ($errors->has('status'))
        <div x-show="alertVisible" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-700">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span class="flex-1">{{ $errors->first('status') }}</span>
            <button type="button" @click="alertVisible = false"
                class="shrink-0 text-rose-400 transition-colors hover:text-rose-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    <!-- ===== Form (inline) ===== -->
    @if ($showForm)
        <x-tenant-form
            :editing-tenant-id="$editingTenantId"
            :code="$code"
            :name="$name"
            :tax-id="$taxId"
            :timezone="$timezone"
            :local-currency="$localCurrency"
            :active="$active"
            :admin-name="$adminName"
            :admin-email="$adminEmail"
            :admin-password="$adminPassword"
            :timezones="$timezones"
        />
    @endif

    <!-- ===== Search & filter ===== -->
    <section class="card p-4">
        <div class="grid gap-3 sm:grid-cols-[1fr_12rem]">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                    fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Buscar por código, nombre o NIT" class="input pl-10" aria-label="Buscar empresas">
                <div wire:loading.delay wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="h-4 w-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="3"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                        </path>
                    </svg>
                </div>
            </div>
            <select wire:model.live="status" class="input" aria-label="Filtrar por estado">
                <option value="">Todas</option>
                <option value="active">Activas</option>
                <option value="inactive">Inactivas</option>
            </select>
        </div>
    </section>

    <!-- ===== Desktop table ===== -->
    <section class="hidden overflow-hidden card md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head">Empresa</th>
                        <th class="table-head">NIT</th>
                        <th class="table-head">Zona horaria</th>
                        <th class="table-head">Moneda</th>
                        <th class="table-head">Usuarios</th>
                        <th class="table-head">Estado</th>
                        <th class="table-head text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line" wire:loading.delay.class="opacity-40"
                    wire:target="search,status,toggleActive">
                    @forelse ($tenants as $tenant)
                        <tr wire:key="tenant-{{ $tenant->id }}" class="transition-colors hover:bg-canvas/60">
                            {{-- Empresa --}}
                            <td class="whitespace-nowrap px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-xs font-bold text-brand-700">
                                        {{ mb_strtoupper(mb_substr($tenant->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-ink">{{ $tenant->name }}</p>
                                        <p class="font-mono text-xs text-muted">{{ $tenant->code }}</p>
                                    </div>
                                </div>
                            </td>
                            {{-- NIT --}}
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $tenant->tax_id ?? '—' }}
                            </td>
                            {{-- Zona horaria --}}
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $tenant->timezone }}</td>
                            {{-- Moneda --}}
                            <td class="whitespace-nowrap px-4 py-4">
                                <span
                                    class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                    {{ $tenant->local_currency }}
                                </span>
                            </td>
                            {{-- Usuarios --}}
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    <span class="tabular-nums">{{ $tenant->memberships_count }}</span>
                                </span>
                            </td>
                            {{-- Estado --}}
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($tenant->active)
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Activa
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        Inactiva
                                    </span>
                                @endif
                            </td>
                            {{-- Acciones --}}
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('platform.tenants.manage', $tenant) }}">
                                        @csrf
                                        <button type="submit"
                                            @disabled(! $tenant->active || $currentTenantId === $tenant->id)
                                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                            </svg>
                                            {{ $currentTenantId === $tenant->id ? 'Gestionando' : 'Gestionar' }}
                                        </button>
                                    </form>
                                    <button type="button" wire:click="edit('{{ $tenant->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-700 transition-colors hover:bg-brand-50">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                        Editar
                                    </button>
                                    <button type="button" wire:click="toggleActive('{{ $tenant->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-gray-100 hover:text-ink">
                                        @if ($tenant->active)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6 6.228a10.45 10.45 0 015.998-1.728c4.756 0 8.774 3.162 10.066 7.5a10.477 10.477 0 01-3.127 4.318M6 6.228L18 18M6 6.228L3 3.252M18 18l3 3M18 18l3-3" />
                                            </svg>
                                            Desactivar
                                        @else
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            Activar
                                        @endif
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16">
                                <div class="flex flex-col items-center justify-center gap-3 text-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-canvas">
                                        <svg class="h-7 w-7 text-muted" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M16.5 6.75h.75m-.75 3h.75m-.75 3h.75" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-ink">No hay empresas registradas</p>
                                        <p class="mt-0.5 text-sm text-muted">Crea la primera empresa para empezar.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===== Mobile cards ===== -->
    <section class="space-y-3 md:hidden" aria-label="Listado de empresas">
        @forelse ($tenants as $tenant)
            <article wire:key="tenant-card-{{ $tenant->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-sm font-bold text-brand-700">
                            {{ mb_strtoupper(mb_substr($tenant->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ $tenant->name }}</p>
                            <p class="mt-0.5 font-mono text-xs text-muted">{{ $tenant->code }}</p>
                        </div>
                    </div>

                    @if ($tenant->active)
                        <span
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Activa
                        </span>
                    @else
                        <span
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            Inactiva
                        </span>
                    @endif
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-canvas p-3.5 text-xs">
                    <div>
                        <dt class="font-medium text-muted">NIT</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $tenant->tax_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Usuarios</dt>
                        <dd class="mt-1 inline-flex items-center gap-1 font-semibold text-ink">
                            <svg class="h-3.5 w-3.5 text-muted" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            <span class="tabular-nums">{{ $tenant->memberships_count }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Zona horaria</dt>
                        <dd class="mt-1 truncate font-semibold text-ink">{{ $tenant->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Moneda</dt>
                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-600">
                                {{ $tenant->local_currency }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="edit('{{ $tenant->id }}')" class="btn btn-soft btn-sm">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                        Editar
                    </button>
                    <button type="button" wire:click="toggleActive('{{ $tenant->id }}')"
                        class="btn btn-ghost btn-sm">
                        @if ($tenant->active)
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6 6.228a10.45 10.45 0 015.998-1.728c4.756 0 8.774 3.162 10.066 7.5a10.477 10.477 0 01-3.127 4.318M6 6.228L18 18M6 6.228L3 3.252M18 18l3 3M18 18l3-3" />
                            </svg>
                            Desactivar
                        @else
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Activar
                        @endif
                    </button>
                </div>

                <form method="POST" action="{{ route('platform.tenants.manage', $tenant) }}" class="mt-2">
                    @csrf
                    <button type="submit" @disabled(! $tenant->active || $currentTenantId === $tenant->id)
                        class="btn btn-outline btn-sm w-full text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        {{ $currentTenantId === $tenant->id ? 'Gestionando esta empresa' : 'Gestionar empresa' }}
                    </button>
                </form>
            </article>
        @empty
            <div
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-line bg-canvas/50 px-6 py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white">
                    <svg class="h-7 w-7 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M16.5 6.75h.75m-.75 3h.75m-.75 3h.75" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-ink">No hay empresas registradas</p>
                    <p class="mt-0.5 text-sm text-muted">Crea la primera empresa para empezar.</p>
                </div>
            </div>
        @endforelse
    </section>

    @if ($tenants->hasPages())
        <section class="card p-3">
            {{ $tenants->links('components.pagination') }}
        </section>
    @endif
</div>
