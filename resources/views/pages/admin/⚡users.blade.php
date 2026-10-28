<?php

use App\Enums\Permission;
use App\Enums\TenantRole;
use App\Models\SalesRep;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\CurrentTenant;
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

new #[Layout('layouts.app')] #[Title('Usuarios')] class extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?string $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'seller';

    public ?string $salesRepId = null;

    public bool $active = true;

    public string $password = '';

    public function mount(): void
    {
        Gate::authorize(Permission::UsersManage->value);
    }

    public function create(): void
    {
        Gate::authorize(Permission::UsersManage->value);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $userId): void
    {
        Gate::authorize(Permission::UsersManage->value);

        $membership = $this->membership($userId);

        $this->editingUserId = $membership->user_id;
        $this->name = $membership->user->name;
        $this->email = $membership->user->email;
        $this->role = $membership->role->value;
        $this->salesRepId = $membership->sales_rep_id;
        $this->active = $membership->active && $membership->user->active;
        $this->password = '';
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize(Permission::UsersManage->value);

        $tenantId = app(CurrentTenant::class)->requireId();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'role' => ['required', Rule::in(TenantRole::values())],
            'salesRepId' => [
                Rule::requiredIf(fn (): bool => $this->role === TenantRole::Seller->value),
                'nullable',
                'uuid',
                Rule::exists('sales_reps', 'id')->where('tenant_id', $tenantId),
            ],
            'active' => ['boolean'],
            'password' => [
                $this->editingUserId === null ? 'required' : 'nullable',
                'string',
                Password::defaults() ?? Password::default(),
            ],
        ]);

        $salesRepId = $this->role === TenantRole::Seller->value ? $this->salesRepId : null;

        if ($this->editingUserId === null) {
            $this->createUser($tenantId, $salesRepId);

            return;
        }

        $this->updateUser($tenantId, $salesRepId);
    }

    public function toggleActive(string $userId): void
    {
        Gate::authorize(Permission::UsersManage->value);

        $tenantId = app(CurrentTenant::class)->requireId();
        $membership = $this->membership($userId);

        if ($membership->user_id === auth()->id()) {
            $this->addError('status', 'No puedes desactivar tu propia cuenta.');

            return;
        }

        if ($membership->active && $membership->role === TenantRole::Admin) {
            $otherAdmins = TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('role', TenantRole::Admin)
                ->where('active', true)
                ->where('user_id', '!=', $membership->user_id)
                ->count();

            if ($otherAdmins === 0) {
                $this->addError('status', 'Debe existir al menos un administrador activo.');

                return;
            }
        }

        $newState = ! ($membership->active && $membership->user->active);

        DB::transaction(function () use ($membership, $newState, $tenantId): void {
            $membership->user->forceFill(['active' => $newState])->save();
            $membership->forceFill(['active' => $newState])->save();

            app(AuditLogger::class)->record(
                $tenantId,
                $newState ? 'user.activated' : 'user.deactivated',
                'user',
                $membership->user_id,
                ['email' => $membership->user->email],
                auth()->user(),
            );
        });

        $this->resetForm();
        session()->flash('status', $newState ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        return $this->view()->with([
            'memberships' => TenantUser::query()
                ->with(['user', 'salesRep'])
                ->join('users', 'users.id', '=', 'tenant_users.user_id')
                ->where('tenant_users.tenant_id', $tenantId)
                ->orderByRaw('lower(users.name)')
                ->select('tenant_users.*')
                ->paginate(25)
                ->onEachSide(1)
                ->withQueryString(),
            'salesReps' => SalesRep::query()->where('active', true)->orderBy('name')->get(),
            'roles' => TenantRole::cases(),
        ]);
    }

    private function createUser(string $tenantId, ?string $salesRepId): void
    {
        $user = DB::transaction(function () use ($tenantId, $salesRepId): User {
            $user = User::create([
                'name' => $this->name,
                'email' => Str::lower($this->email),
                'password_hash' => Hash::make($this->password),
                'active' => $this->active,
            ]);

            $membership = TenantUser::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'role' => $this->role,
                'sales_rep_id' => $salesRepId,
                'active' => $this->active,
            ]);

            app(AuditLogger::class)->record(
                $tenantId,
                'user.created',
                'user',
                $user->id,
                [
                    'email' => $user->email,
                    'role' => $membership->role->value,
                    'active' => $this->active,
                ],
                auth()->user(),
            );

            return $user;
        });

        $this->resetForm();
        session()->flash('status', 'Usuario '.$user->name.' creado.');
    }

    private function updateUser(string $tenantId, ?string $salesRepId): void
    {
        $membership = $this->membership((string) $this->editingUserId);
        $isSelf = $membership->user_id === auth()->id();

        if ($isSelf && (! $this->active || $this->role !== TenantRole::Admin->value)) {
            $this->addError('role', 'No puedes desactivar tu cuenta ni quitarte el rol de administrador.');

            return;
        }

        if ($membership->role === TenantRole::Admin
            && (! $this->active || $this->role !== TenantRole::Admin->value)) {
            $otherAdmins = TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('role', TenantRole::Admin)
                ->where('active', true)
                ->where('user_id', '!=', $membership->user_id)
                ->count();

            if ($otherAdmins === 0) {
                $this->addError('role', 'Debe existir al menos un administrador activo.');

                return;
            }
        }

        DB::transaction(function () use ($membership, $salesRepId, $tenantId): void {
            $user = $membership->user;

            $user->forceFill([
                'name' => $this->name,
                'email' => Str::lower($this->email),
                'active' => $this->active,
            ]);

            if ($this->password !== '') {
                $user->forceFill(['password_hash' => Hash::make($this->password)]);
            }

            $user->save();

            $membership->forceFill([
                'role' => $this->role,
                'sales_rep_id' => $salesRepId,
                'active' => $this->active,
            ])->save();

            app(AuditLogger::class)->record(
                $tenantId,
                'user.updated',
                'user',
                $user->id,
                [
                    'email' => $user->email,
                    'role' => $membership->role->value,
                    'active' => $this->active,
                    'password_changed' => $this->password !== '',
                ],
                auth()->user(),
            );
        });

        $this->resetForm();
        session()->flash('status', 'Usuario actualizado.');
    }

    private function membership(string $userId): TenantUser
    {
        return TenantUser::query()
            ->with('user')
            ->where('tenant_id', app(CurrentTenant::class)->requireId())
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->reset('showForm', 'editingUserId', 'name', 'email', 'role', 'salesRepId', 'active', 'password');
        $this->role = TenantRole::Seller->value;
        $this->active = true;
        $this->resetValidation();
    }
};
?>

<div class="space-y-5 sm:space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Administración</p>
            <h1 class="page-title">Usuarios</h1>
            <p class="mt-1.5 text-sm text-muted">Gestiona las cuentas de tu empresa y sus roles.</p>
        </div>

        <button type="button" wire:click="create"
            class="btn btn-primary w-full sm:w-auto">
            Nuevo usuario
        </button>
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
                    <h2 class="text-base font-semibold text-ink">{{ $editingUserId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
                    <p class="mt-1 text-sm text-muted">La contraseña es temporal y el usuario puede cambiarla después.</p>
                </div>
                <button type="button" wire:click="cancel" class="text-sm font-medium text-muted hover:text-ink">Cancelar</button>
            </div>

            <form wire:submit="save" class="mt-6 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="user-name" class="label">Nombre</label>
                        <input id="user-name" type="text" wire:model="name" required
                            class="input">
                        @error('name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="user-email" class="label">Correo electrónico</label>
                        <input id="user-email" type="email" wire:model="email" required
                            class="input">
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="user-role" class="label">Rol</label>
                        <select id="user-role" wire:model.live="role"
                            class="input">
                            @foreach ($roles as $tenantRole)
                                <option value="{{ $tenantRole->value }}">{{ $tenantRole->label() }}</option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="user-password" class="label">
                            {{ $editingUserId ? 'Nueva contraseña (opcional)' : 'Contraseña temporal' }}
                        </label>
                        <input id="user-password" type="password" wire:model="password" autocomplete="new-password"
                            class="input">
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($role === \App\Enums\TenantRole::Seller->value)
                        <div>
                            <label for="user-sales-rep" class="label">Vendedor asignado</label>
                            <select id="user-sales-rep" wire:model="salesRepId"
                                class="input">
                                <option value="">Selecciona un vendedor</option>
                                @foreach ($salesReps as $salesRep)
                                    <option value="{{ $salesRep->id }}">{{ $salesRep->name }}</option>
                                @endforeach
                            </select>
                            @error('salesRepId')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <label class="flex items-center gap-2 self-end pb-2 label">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-line text-brand-700 focus:ring-brand-500">
                        Usuario activo
                    </label>
                </div>

                @error('status')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <button type="submit" wire:loading.attr="disabled"
                    class="btn btn-dark w-full sm:w-auto">
                    {{ $editingUserId ? 'Guardar cambios' : 'Crear usuario' }}
                </button>
            </form>
        </section>
    @endif

    <section class="overflow-hidden card">
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head">Usuario</th>
                        <th class="table-head">Rol</th>
                        <th class="table-head">Vendedor</th>
                        <th class="table-head">Estado</th>
                        <th class="table-head text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($memberships as $membership)
                        <tr wire:key="membership-{{ $membership->id }}">
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $membership->user->name }}</p>
                                <p class="text-xs text-muted">{{ $membership->user->email }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $membership->role->label() }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-ink-soft">{{ $membership->salesRep?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($membership->active && $membership->user->active)
                                    <span class="badge bg-emerald-100 text-emerald-700">Activo</span>
                                @else
                                    <span class="badge bg-black/[0.04] text-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                <button type="button" wire:click="edit('{{ $membership->user_id }}')" class="font-medium text-brand-700 hover:text-brand-800">
                                    Editar
                                </button>
                                <button type="button" wire:click="toggleActive('{{ $membership->user_id }}')" class="ml-4 font-medium text-muted hover:text-ink">
                                    {{ $membership->active && $membership->user->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-muted">No hay usuarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Listado de usuarios">
        @forelse ($memberships as $membership)
            <article wire:key="membership-card-{{ $membership->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $membership->user->name }}</p>
                        <p class="mt-0.5 truncate text-xs text-muted">{{ $membership->user->email }}</p>
                    </div>

                    @if ($membership->active && $membership->user->active)
                        <span class="shrink-0 badge bg-emerald-100 text-emerald-700">Activo</span>
                    @else
                        <span class="shrink-0 badge bg-black/[0.04] text-muted">Inactivo</span>
                    @endif
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">Rol</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $membership->role->label() }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Vendedor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $membership->salesRep?->name ?? '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="edit('{{ $membership->user_id }}')"
                        class="btn btn-soft btn-sm">
                        Editar
                    </button>
                    <button type="button" wire:click="toggleActive('{{ $membership->user_id }}')"
                        class="btn btn-ghost btn-sm">
                        {{ $membership->active && $membership->user->active ? 'Desactivar' : 'Activar' }}
                    </button>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">No hay usuarios registrados.</p>
                <p class="mt-1 text-sm text-muted">Crea el primer usuario para empezar.</p>
            </div>
        @endforelse
    </section>

    @if ($memberships->hasPages())
        <section class="card p-3">
            {{ $memberships->links('components.pagination') }}
        </section>
    @endif
</div>
