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
        ];

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
        ];

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