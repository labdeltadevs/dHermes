<?php

use App\Enums\Permission;
use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\SalesRep;
use App\Models\TenantUser;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Asignaciones')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $collectorFilter = '';

    public string $salesRepId = '';

    public string $active = '';

    /** @var array<int, string> */
    public array $selected = [];

    public string $bulkCollectorId = '';

    private const AUDIT_CUSTOMER_IDS_LIMIT = 500;

    public function mount(): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'collectorFilter', 'salesRepId', 'active'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'collectorFilter', 'salesRepId', 'active']);
        $this->resetPage();
    }

    public function assignSingle(string $customerId, string $collectorId = ''): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);

        if ($collectorId === '') {
            $this->applyCollector([$customerId], null, 'collector');

            return;
        }

        $this->applyCollector([$customerId], $collectorId, 'collector');
    }

    public function unassignSingle(string $customerId): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);

        $this->applyCollector([$customerId], null, 'collector');
    }

    public function assignSelected(): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);

        $this->applyCollector($this->selected, $this->bulkCollectorId, 'bulkCollectorId');
    }

    public function unassignSelected(): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);

        $this->applyCollector($this->selected, null, 'bulkCollectorId');
    }

    public function assignAll(): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);

        $this->applyCollectorToFiltered($this->bulkCollectorId);
    }

    public function unassignAll(): void
    {
        Gate::authorize(Permission::AssignmentsManage->value);

        $this->applyCollectorToFiltered(null);
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->requireId();

        return $this->view()->with([
            'customers' => $this->customersQuery()
                ->paginate(25)
                ->onEachSide(1)
                ->withQueryString(),
            'filteredCount' => $this->customersQuery()->count(),
            'collectors' => $this->collectorsQuery()->get(),
            'salesReps' => SalesRep::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slpcode']),
        ]);
    }

    /**
     * @return Builder<Customer>
     */
    private function customersQuery(): Builder
    {
        $query = Customer::query()
            ->with(['collector:id,name,email', 'salesRep:id,name,slpcode']);

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('cardcode', '%'.$search.'%');
            });
        }

        match ($this->collectorFilter) {
            'unassigned' => $query->whereNull('collector_user_id'),
            'assigned' => $query->whereNotNull('collector_user_id'),
            default => $this->collectorFilter !== ''
                ? $query->where('collector_user_id', $this->collectorFilter)
                : null,
        };

        if ($this->salesRepId !== '') {
            $query->where('sales_rep_id', $this->salesRepId);
        }

        if ($this->active !== '') {
            $query->where('active', $this->active === '1');
        }

        return $query->orderByRaw('lower(name)');
    }

    /**
     * @return Builder<TenantUser>
     */
    private function collectorsQuery(): Builder
    {
        return TenantUser::query()
            ->join('users', 'users.id', '=', 'tenant_users.user_id')
            ->where('tenant_users.tenant_id', app(CurrentTenant::class)->requireId())
            ->where('tenant_users.role', TenantRole::Collector)
            ->where('tenant_users.active', true)
            ->where('users.active', true)
            ->orderByRaw('lower(users.name)')
            ->select('tenant_users.*')
            ->with('user');
    }

    /**
     * @return array<int, string>
     */
    private function collectorIds(): array
    {
        return $this->collectorsQuery()->pluck('tenant_users.user_id')->all();
    }

    /**
     * @param  array<int, mixed>  $customerIds
     * @param  array<string, string>  $filters
     */
    private function applyCollector(array $customerIds, ?string $collectorId, string $errorKey, array $filters = []): void
    {
        $tenantId = app(CurrentTenant::class)->requireId();

        $ids = array_values(array_unique(array_filter(
            $customerIds,
            fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            $this->addError($errorKey, 'Selecciona al menos un cliente.');

            return;
        }

        $this->validateCollector($collectorId, $errorKey);

        $customers = Customer::query()->whereIn('id', $ids)->pluck('id')->all();

        if ($customers === []) {
            $this->addError($errorKey, 'No se encontraron clientes para asignar.');

            return;
        }

        $assigned = $collectorId !== null;

        DB::transaction(function () use ($tenantId, $customers, $collectorId, $assigned, $filters): void {
            Customer::query()
                ->whereIn('id', $customers)
                ->update(['collector_user_id' => $collectorId]);

            app(AuditLogger::class)->record(
                $tenantId,
                $assigned ? 'customer.collector_assigned' : 'customer.collector_unassigned',
                'customer',
                null,
                $this->auditDetails($customers, $collectorId, $filters),
                auth()->user(),
            );
        });

        $this->reset('selected', 'bulkCollectorId');
        $this->resetErrorBag($errorKey);
        session()->flash('status', $assigned
            ? 'Clientes asignados al cobrador.'
            : 'Clientes sin cobrador asignado.');
    }

    private function applyCollectorToFiltered(?string $collectorId): void
    {
        $errorKey = 'bulkCollectorId';

        $this->validateCollector($collectorId, $errorKey);

        $query = $this->customersQuery();
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->addError($errorKey, 'No hay clientes con los filtros actuales.');

            return;
        }

        $customerIds = $count <= self::AUDIT_CUSTOMER_IDS_LIMIT
            ? (clone $query)->pluck('id')->all()
            : [];

        $tenantId = app(CurrentTenant::class)->requireId();
        $assigned = $collectorId !== null;
        $filters = $this->activeFilters();

        DB::transaction(function () use ($query, $tenantId, $collectorId, $assigned, $count, $customerIds, $filters): void {
            $query->update(['collector_user_id' => $collectorId]);

            app(AuditLogger::class)->record(
                $tenantId,
                $assigned ? 'customer.collector_assigned' : 'customer.collector_unassigned',
                'customer',
                null,
                $this->auditDetails($customerIds, $collectorId, $filters, $count),
                auth()->user(),
            );
        });

        $this->reset('selected', 'bulkCollectorId');
        $this->resetErrorBag($errorKey);
        $this->dispatch('bulk-assignment-completed');
        session()->flash('status', $assigned
            ? sprintf('%d clientes asignados al cobrador.', $count)
            : sprintf('Se quitó el cobrador a %d clientes.', $count));
    }

    private function validateCollector(?string $collectorId, string $errorKey): void
    {
        if ($collectorId !== null && ! in_array($collectorId, $this->collectorIds(), true)) {
            throw ValidationException::withMessages([
                $errorKey => 'El cobrador seleccionado no pertenece a la empresa.',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $customerIds
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function auditDetails(array $customerIds, ?string $collectorId, array $filters = [], ?int $count = null): array
    {
        $count ??= count($customerIds);

        $details = [
            'count' => $count,
            'collector_user_id' => $collectorId,
        ];

        if ($count <= self::AUDIT_CUSTOMER_IDS_LIMIT) {
            $details['customer_ids'] = $customerIds;
        }

        if ($filters !== []) {
            $details['filters'] = $filters;
        }

        return $details;
    }

    /**
     * @return array<string, string>
     */
    private function activeFilters(): array
    {
        return array_filter([
            'search' => trim($this->search),
            'collector' => $this->collectorFilter,
            'sales_rep_id' => $this->salesRepId,
            'active' => $this->active,
        ], fn (string $value): bool => $value !== '');
    }
};
?>

@php
    $selectedCount = count($selected);
    $bulkCollectorName = $bulkCollectorId !== ''
        ? $collectors->firstWhere('user_id', $bulkCollectorId)?->user?->name
        : null;
    $activeFilterLabels = array_filter([
        $search !== '' ? 'Búsqueda: "'.$search.'"' : null,
        match ($collectorFilter) {
            'unassigned' => 'Sin cobrador',
            'assigned' => 'Con cobrador',
            default => $collectorFilter !== '' ? 'Cobrador filtrado' : null,
        },
        $salesRepId !== '' ? 'Vendedor filtrado' : null,
        $active !== '' ? ($active === '1' ? 'Solo activos' : 'Solo inactivos') : null,
    ]);
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-breadcrumb :items="[
                ['label' => 'Inicio', 'url' => route('dashboard')],
                ['label' => 'Administración'],
                ['label' => 'Asignaciones'],
            ]" />
            <h1 class="page-title">Asignaciones</h1>
            <p class="mt-1.5 text-sm text-muted">Asigna clientes a los cobradores de {{ auth()->user()->currentMembership()?->tenant?->name ?? 'tu empresa' }}.</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div wire:loading.delay
                wire:target="search, collectorFilter, salesRepId, active, assignSingle, assignSelected, unassignSelected, unassignSingle"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-100 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-800">
                <svg class="size-4 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="3"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                    </path>
                </svg>
                <span>Procesando grupo seleccionado…</span>
            </div>

            @if ($search !== '' || $collectorFilter !== '' || $salesRepId !== '' || $active !== '')
                <button type="button" wire:click="clearFilters"
                    class="btn btn-outline">
                    Limpiar filtros
                </button>
            @endif
        </div>
    </section>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="card p-5 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label for="assignments-search" class="label">Buscar</label>
                <div class="relative">
                    <input id="assignments-search" type="search" wire:model.live.debounce.400ms="search"
                        placeholder="Nombre o código del cliente"
                        class="input">
                    <div wire:loading.delay wire:target="search"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                        <svg class="size-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>

            <div>
                <label for="assignments-collector" class="label">Cobrador</label>
                <div class="relative">
                    <select id="assignments-collector" wire:model.live="collectorFilter"
                        class="input">
                        <option value="">Todos</option>
                        <option value="unassigned">Sin cobrador</option>
                        <option value="assigned">Con cobrador</option>
                        @foreach ($collectors as $collector)
                            <option value="{{ $collector->user_id }}">{{ $collector->user->name }}</option>
                        @endforeach
                    </select>
                    <div wire:loading.delay wire:target="collectorFilter"
                        class="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2">
                        <svg class="size-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>

            <div>
                <label for="assignments-sales-rep" class="label">Vendedor</label>
                <div class="relative">
                    <select id="assignments-sales-rep" wire:model.live="salesRepId"
                        class="input">
                        <option value="">Todos</option>
                        @foreach ($salesReps as $salesRep)
                            <option value="{{ $salesRep->id }}">{{ $salesRep->name }} · SLP {{ $salesRep->slpcode }}</option>
                        @endforeach
                    </select>
                    <div wire:loading.delay wire:target="salesRepId"
                        class="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2">
                        <svg class="size-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>

            <div>
                <label for="assignments-active" class="label">Estado</label>
                <div class="relative">
                    <select id="assignments-active" wire:model.live="active"
                        class="input">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                    <div wire:loading.delay wire:target="active"
                        class="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2">
                        <svg class="size-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ bulkOpen: false, bulkMode: 'assign' }"
            x-on:bulk-assignment-completed.window="bulkOpen = false"
            class="mt-4 space-y-3 rounded-xl bg-canvas p-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="sm:min-w-64">
                    <label for="assignments-bulk-collector" class="label">Asignar a</label>
                    <select id="assignments-bulk-collector" wire:model.live="bulkCollectorId"
                        class="input">
                        <option value="">Selecciona un cobrador</option>
                        @foreach ($collectors as $collector)
                            <option value="{{ $collector->user_id }}">{{ $collector->user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted sm:ml-auto">
                    <span><span class="font-semibold text-ink">{{ $filteredCount }}</span> en filtros</span>
                    <span aria-hidden="true">·</span>
                    <span><span class="font-semibold text-ink">{{ $selectedCount }}</span> seleccionados</span>
                </div>
            </div>

            @if ($activeFilterLabels !== [])
                <p class="text-xs text-muted">Filtros activos: {{ implode(' · ', $activeFilterLabels) }}</p>
            @endif

            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <button type="button" x-on:click="bulkMode = 'assign'; bulkOpen = true"
                    wire:loading.attr="disabled"
                    wire:target="assignAll, assignSelected, unassignAll, unassignSelected"
                    @disabled($filteredCount === 0 || $bulkCollectorId === '')
                    class="btn btn-primary">
                    Asignar los {{ $filteredCount }} clientes
                </button>

                <button type="button" wire:click="assignSelected"
                    wire:loading.attr="disabled"
                    wire:target="assignAll, assignSelected, unassignAll, unassignSelected"
                    @disabled($selectedCount === 0 || $bulkCollectorId === '')
                    class="btn btn-outline">
                    Asignar los {{ $selectedCount }} seleccionados
                </button>

                <button type="button" x-on:click="bulkMode = 'unassign'; bulkOpen = true"
                    wire:loading.attr="disabled"
                    wire:target="assignAll, assignSelected, unassignAll, unassignSelected"
                    @disabled($filteredCount === 0)
                    class="btn btn-outline">
                    Quitar a los {{ $filteredCount }}
                </button>

                <button type="button" wire:click="unassignSelected"
                    wire:loading.attr="disabled"
                    wire:target="assignAll, assignSelected, unassignAll, unassignSelected"
                    @disabled($selectedCount === 0)
                    class="btn btn-outline">
                    Quitar a los {{ $selectedCount }} seleccionados
                </button>
            </div>

            @error('bulkCollectorId')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror

            <div x-show="bulkOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
                <div x-show="bulkOpen" x-transition.opacity class="absolute inset-0 bg-ink/40 backdrop-blur-sm"
                    x-on:click="bulkOpen = false"></div>

                <div x-show="bulkOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-y-4 opacity-0 sm:scale-95"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                    x-transition:leave-end="translate-y-4 opacity-0 sm:scale-95"
                    role="dialog" aria-modal="true" aria-labelledby="bulk-confirm-title"
                    class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-5 shadow-pop">
                    <h2 id="bulk-confirm-title" class="text-base font-semibold text-ink">
                        <span x-show="bulkMode === 'assign'">Asignar todos los clientes filtrados</span>
                        <span x-show="bulkMode === 'unassign'">Quitar el cobrador a todos los clientes filtrados</span>
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-muted" x-show="bulkMode === 'assign'">
                        Se asignarán <span class="font-semibold text-ink">{{ $filteredCount }} clientes</span>
                        a <span class="font-semibold text-ink">{{ $bulkCollectorName ?? 'el cobrador seleccionado' }}</span>.
                        Quienes ya tengan un cobrador serán reasignados.
                    </p>

                    <p class="mt-2 text-sm leading-6 text-muted" x-show="bulkMode === 'unassign'">
                        Se quitará el cobrador a <span class="font-semibold text-ink">{{ $filteredCount }} clientes</span>.
                    </p>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="bulkOpen = false" class="btn btn-outline">
                            Cancelar
                        </button>
                        <button type="button" wire:click="assignAll"
                            wire:loading.attr="disabled" wire:target="assignAll"
                            x-show="bulkMode === 'assign'" class="btn btn-primary">
                            <span wire:loading.remove wire:target="assignAll">Asignar {{ $filteredCount }}</span>
                            <span wire:loading wire:target="assignAll">Asignando {{ $filteredCount }} clientes...</span>
                        </button>
                        <button type="button" wire:click="unassignAll"
                            wire:loading.attr="disabled" wire:target="unassignAll"
                            x-show="bulkMode === 'unassign'" class="btn btn-danger">
                            <span wire:loading.remove wire:target="unassignAll">Quitar a {{ $filteredCount }}</span>
                            <span wire:loading wire:target="unassignAll">Quitando cobrador a {{ $filteredCount }} clientes...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if ($collectors->isNotEmpty())
            <div class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Cobradores activos</p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($collectors as $collector)
                        <li wire:key="collector-chip-{{ $collector->id }}" class="chip">
                            <span class="font-semibold text-ink">{{ $collector->user->name }}</span>
                            <span class="text-muted">{{ $collector->user->email }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @error('bulkCollectorId')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
        @error('collector')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </section>

    <section class="hidden overflow-hidden card md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head">
                            <span class="sr-only">Seleccionar</span>
                        </th>
                        <th class="table-head">Cliente</th>
                        <th class="table-head">Vendedor</th>
                        <th class="table-head">Cobrador</th>
                        <th class="table-head">Estado</th>
                        <th class="table-head">Asignar a</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line" wire:loading.delay.class="opacity-40"
                    wire:target="search, collectorFilter, salesRepId, active, assignSingle, assignSelected, unassignSelected, unassignSingle">
                    @forelse ($customers as $customer)
                        <tr wire:key="assignment-{{ $customer->id }}" class="transition hover:bg-brand-50/40">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selected" value="{{ $customer->id }}"
                                    aria-label="Seleccionar {{ $customer->name }}"
                                    class="size-4 rounded border-line text-brand-700 focus:ring-brand-500">
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $customer->name }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $customer->cardcode }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-ink-soft">{{ $customer->salesRep?->name ?? '—' }}</td>
                            <td class="px-4 py-4 text-sm text-ink-soft">{{ $customer->collector?->name ?? 'Sin asignar' }}</td>
                            <td class="px-4 py-4">
                                @if ($customer->active)
                                    <span class="badge bg-emerald-100 text-emerald-700">Activo</span>
                                @else
                                    <span class="badge bg-black/[0.04] text-muted">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <select wire:change="assignSingle('{{ $customer->id }}', $event.target.value)"
                                    wire:loading.attr="disabled" wire:target="assignSingle"
                                    aria-label="Cobrador de {{ $customer->name }}"
                                    class="select">
                                    <option value="">Sin asignar</option>
                                    @foreach ($collectors as $collector)
                                        <option value="{{ $collector->user_id }}" @selected($customer->collector_user_id === $collector->user_id)>
                                            {{ $collector->user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin clientes con estos filtros.</p>
                                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                                    Limpiar filtros
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Asignación de clientes" wire:loading.delay.class="opacity-40"
        wire:target="search, collectorFilter, salesRepId, active, assignSingle, assignSelected, unassignSelected, unassignSingle">
        @forelse ($customers as $customer)
            <article wire:key="assignment-card-{{ $customer->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <input type="checkbox" wire:model.live="selected" value="{{ $customer->id }}"
                            aria-label="Seleccionar {{ $customer->name }}"
                            class="mt-0.5 size-4 rounded border-line text-brand-700 focus:ring-brand-500">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ $customer->name }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ $customer->cardcode }}</p>
                        </div>
                    </div>

                    @if ($customer->active)
                        <span class="shrink-0 badge bg-emerald-100 text-emerald-700">Activo</span>
                    @else
                        <span class="shrink-0 badge bg-black/[0.04] text-muted">Inactivo</span>
                    @endif
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">Vendedor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $customer->salesRep?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Cobrador</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $customer->collector?->name ?? 'Sin asignar' }}</dd>
                    </div>
                </dl>

                <div class="mt-3">
                    <label for="assignment-card-collector-{{ $customer->id }}" class="label">Asignar a</label>
                    <select id="assignment-card-collector-{{ $customer->id }}"
                        wire:change="assignSingle('{{ $customer->id }}', $event.target.value)"
                        wire:loading.attr="disabled" wire:target="assignSingle"
                        class="select">
                        <option value="">Sin asignar</option>
                        @foreach ($collectors as $collector)
                            <option value="{{ $collector->user_id }}" @selected($customer->collector_user_id === $collector->user_id)>
                                {{ $collector->user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">Sin clientes con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>

    @if ($customers->hasPages())
        <section class="card p-3">
            {{ $customers->links('components.pagination') }}
        </section>
    @endif
</div>
