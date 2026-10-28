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

new #[Layout('layouts.app')] #[Title('Empresas')] class extends Component
{
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

        $newState = ! $tenant->active;

        $tenant->update(['active' => $newState]);

        app(AuditLogger::class)->record(
            $tenant->id,
            $newState ? 'tenant.activated' : 'tenant.deactivated',
            'tenant',
            $tenant->id,
            ['code' => $tenant->code, 'name' => $tenant->name],
            auth()->user(),
        );

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
            ->withCount(['memberships' => fn (Builder $query): Builder => $query->withoutGlobalScope('tenant')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $like = '%'.$search.'%';

                    $query->whereLike('code', $like)
                        ->orWhereLike('name', $like)
                        ->orWhereLike('tax_id', $like);
                });
            })
            ->when($this->status === 'active', fn (Builder $query): Builder => $query->where('active', true))
            ->when($this->status === 'inactive', fn (Builder $query): Builder => $query->where('active', false))
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
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]{2,20}$/',
                Rule::unique('tenants', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'localCurrency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'active' => ['boolean'],
            'adminName' => [Rule::requiredIf($existingAdmin === null), 'nullable', 'string', 'max:255'],
            'adminEmail' => ['required', 'string', 'email', 'max:255'],
            'adminPassword' => [
                Rule::requiredIf($existingAdmin === null),
                'nullable',
                'string',
                Password::defaults(),
            ],
        ]);

        if ($existingAdmin !== null && ! $existingAdmin->active) {
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

            $admin = $existingAdmin ?? User::create([
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
        session()->flash('status', 'Empresa '.$tenant->name.' creada.');
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

        return $email === ''
            ? null
            : User::query()->where('email', $email)->first();
    }

    private function resetForm(): void
    {
        $this->reset(
            'showForm',
            'editingTenantId',
            'code',
            'name',
            'taxId',
            'timezone',
            'localCurrency',
            'active',
            'adminName',
            'adminEmail',
            'adminPassword',
        );

        $this->timezone = 'America/La_Paz';
        $this->localCurrency = 'BOB';
        $this->active = true;
        $this->resetValidation();
    }
};
?>

<div class="space-y-5 sm:space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Plataforma</p>
            <h1 class="page-title">Empresas</h1>
            <p class="mt-1.5 text-sm text-muted">Crea y administra los tenants del sistema.</p>
        </div>

        <button type="button" wire:click="create" class="btn btn-primary w-full sm:w-auto">
            Nueva empresa
        </button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="text-xs font-medium text-muted">Total</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ $stats['total'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-muted">Activas</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $stats['active'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-muted">Inactivas</p>
            <p class="mt-1 text-2xl font-semibold text-muted">{{ $stats['inactive'] }}</p>
        </div>
    </div>

    @if ($errors->has('status'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first('status') }}
        </div>
    @endif

    @if ($showForm)
        <section class="card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-ink">{{ $editingTenantId ? 'Editar empresa' : 'Nueva empresa' }}</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ $editingTenantId ? 'El código de la empresa no se puede modificar.' : 'Al crearla se provisionan sus roles y su administrador inicial.' }}
                    </p>
                </div>
                <button type="button" wire:click="cancel" class="text-sm font-medium text-muted hover:text-ink">Cancelar</button>
            </div>

            <form wire:submit="save" class="mt-6 space-y-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="tenant-code" class="label">Código</label>
                        <input id="tenant-code" type="text" wire:model="code" maxlength="20"
                            class="input uppercase disabled:bg-canvas disabled:text-muted" @disabled($editingTenantId !== null)>
                        @error('code')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tenant-name" class="label">Nombre</label>
                        <input id="tenant-name" type="text" wire:model="name" required class="input">
                        @error('name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tenant-tax-id" class="label">NIT</label>
                        <input id="tenant-tax-id" type="text" wire:model="taxId" class="input">
                        @error('taxId')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tenant-timezone" class="label">Zona horaria</label>
                        <select id="tenant-timezone" wire:model="timezone" class="input">
                            @foreach ($timezones as $zone)
                                <option value="{{ $zone }}">{{ $zone }}</option>
                            @endforeach
                        </select>
                        @error('timezone')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="tenant-currency" class="label">Moneda local</label>
                        <input id="tenant-currency" type="text" wire:model="localCurrency" maxlength="3"
                            class="input uppercase">
                        @error('localCurrency')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 self-end pb-2 label">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-line text-brand-700 focus:ring-brand-500">
                        Empresa activa
                    </label>
                </div>

                @if ($editingTenantId === null)
                    <div class="rounded-xl border border-line bg-canvas p-4 sm:p-5">
                        <h3 class="text-sm font-semibold text-ink">Administrador inicial</h3>
                        <p class="mt-1 text-sm text-muted">
                            Si el correo ya existe, se adjunta como administrador sin cambiar su contraseña.
                        </p>

                        <div class="mt-4 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="tenant-admin-name" class="label">Nombre</label>
                                <input id="tenant-admin-name" type="text" wire:model="adminName" class="input">
                                @error('adminName')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tenant-admin-email" class="label">Correo electrónico</label>
                                <input id="tenant-admin-email" type="email" wire:model.live.debounce.400ms="adminEmail" class="input">
                                @error('adminEmail')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tenant-admin-password" class="label">Contraseña temporal</label>
                                <input id="tenant-admin-password" type="password" wire:model="adminPassword"
                                    autocomplete="new-password" class="input">
                                @error('adminPassword')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @error('status')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <button type="submit" wire:loading.attr="disabled" class="btn btn-dark w-full sm:w-auto">
                    {{ $editingTenantId ? 'Guardar cambios' : 'Crear empresa' }}
                </button>
            </form>
        </section>
    @endif

    <section class="card p-4">
        <div class="grid gap-3 sm:grid-cols-[1fr_12rem]">
            <input type="search" wire:model.live.debounce.400ms="search"
                placeholder="Buscar por código, nombre o NIT" class="input" aria-label="Buscar empresas">
            <select wire:model.live="status" class="input" aria-label="Filtrar por estado">
                <option value="">Todas</option>
                <option value="active">Activas</option>
                <option value="inactive">Inactivas</option>
            </select>
        </div>
    </section>

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
                <tbody class="divide-y divide-line">
                    @forelse ($tenants as $tenant)
                        <tr wire:key="tenant-{{ $tenant->id }}">
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $tenant->name }}</p>
                                <p class="text-xs text-muted">{{ $tenant->code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $tenant->tax_id ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $tenant->timezone }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $tenant->local_currency }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $tenant->memberships_count }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($tenant->active)
                                    <span class="badge bg-emerald-100 text-emerald-700">Activa</span>
                                @else
                                    <span class="badge bg-black/[0.04] text-muted">Inactiva</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                <button type="button" wire:click="edit('{{ $tenant->id }}')"
                                    class="font-medium text-brand-700 hover:text-brand-800">
                                    Editar
                                </button>
                                <button type="button" wire:click="toggleActive('{{ $tenant->id }}')"
                                    class="ml-4 font-medium text-muted hover:text-ink">
                                    {{ $tenant->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-muted">No hay empresas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Listado de empresas">
        @forelse ($tenants as $tenant)
            <article wire:key="tenant-card-{{ $tenant->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $tenant->name }}</p>
                        <p class="mt-0.5 truncate text-xs text-muted">{{ $tenant->code }}</p>
                    </div>

                    @if ($tenant->active)
                        <span class="shrink-0 badge bg-emerald-100 text-emerald-700">Activa</span>
                    @else
                        <span class="shrink-0 badge bg-black/[0.04] text-muted">Inactiva</span>
                    @endif
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">NIT</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $tenant->tax_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Usuarios</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $tenant->memberships_count }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Zona horaria</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $tenant->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Moneda</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $tenant->local_currency }}</dd>
                    </div>
                </dl>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="edit('{{ $tenant->id }}')" class="btn btn-soft btn-sm">
                        Editar
                    </button>
                    <button type="button" wire:click="toggleActive('{{ $tenant->id }}')" class="btn btn-ghost btn-sm">
                        {{ $tenant->active ? 'Desactivar' : 'Activar' }}
                    </button>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">No hay empresas registradas.</p>
                <p class="mt-1 text-sm text-muted">Crea la primera empresa para empezar.</p>
            </div>
        @endforelse
    </section>

    @if ($tenants->hasPages())
        <section class="card p-3">
            {{ $tenants->links('components.pagination') }}
        </section>
    @endif
</div>
