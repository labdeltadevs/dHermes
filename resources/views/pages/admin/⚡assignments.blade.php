<?php

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

    public function mount(): void
    {
        Gate::authorize('manage-users');
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
        Gate::authorize('manage-users');

        if ($collectorId === '') {
            $this->applyCollector([$customerId], null, 'collector');

            return;
        }

        $this->applyCollector([$customerId], $collectorId, 'collector');
    }

    public function unassignSingle(string $customerId): void
    {
        Gate::authorize('manage-users');

        $this->applyCollector([$customerId], null, 'collector');
    }

    public function assignSelected(): void
    {
        Gate::authorize('manage-users');

        $this->applyCollector($this->selected, $this->bulkCollectorId, 'bulkCollectorId');
    }

    public function unassignSelected(): void
    {
        Gate::authorize('manage-users');

        $this->applyCollector($this->selected, null, 'bulkCollectorId');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->requireId();

        return $this->view()->with([
            'customers' => $this->customersQuery()
                ->paginate(25)
                ->onEachSide(1)
                ->withQueryString(),
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
     */
    private function applyCollector(array $customerIds, ?string $collectorId, string $errorKey): void
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

        if ($collectorId !== null && ! in_array($collectorId, $this->collectorIds(), true)) {
            throw ValidationException::withMessages([
                $errorKey => 'El cobrador seleccionado no pertenece a la empresa.',
            ]);
        }

        $customers = Customer::query()->whereIn('id', $ids)->pluck('id')->all();

        if ($customers === []) {
            $this->addError($errorKey, 'No se encontraron clientes para asignar.');

            return;
        }

        $assigned = $collectorId !== null;

        DB::transaction(function () use ($tenantId, $customers, $collectorId, $assigned): void {
            Customer::query()
                ->whereIn('id', $customers)
                ->update(['collector_user_id' => $collectorId]);

            app(AuditLogger::class)->record(
                $tenantId,
                $assigned ? 'customer.collector_assigned' : 'customer.collector_unassigned',
                'customer',
                null,
                [
                    'customer_ids' => $customers,
                    'count' => count($customers),
                    'collector_user_id' => $collectorId,
                ],
                auth()->user(),
            );
        });

        $this->reset('selected', 'bulkCollectorId');
        $this->resetErrorBag($errorKey);
        session()->flash('status', $assigned
            ? 'Clientes asignados al cobrador.'
            : 'Clientes sin cobrador asignado.');
    }
};
?>

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Administración</p>
            <h1 class="page-title">Asignaciones</h1>
            <p class="mt-1.5 text-sm text-muted">Asigna clientes a los cobradores de {{ auth()->user()->currentMembership()?->tenant?->name ?? 'tu empresa' }}.</p>
        </div>

        @if ($search !== '' || $collectorFilter !== '' || $salesRepId !== '' || $active !== '')
            <button type="button" wire:click="clearFilters"
                class="btn btn-outline">
                Limpiar filtros
            </button>
        @endif
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
                <input id="assignments-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Nombre o código del cliente"
                    class="input">
            </div>

            <div>
                <label for="assignments-collector" class="label">Cobrador</label>
                <select id="assignments-collector" wire:model.live="collectorFilter"
                    class="input">
                    <option value="">Todos</option>
                    <option value="unassigned">Sin cobrador</option>
                    <option value="assigned">Con cobrador</option>
                    @foreach ($collectors as $collector)
                        <option value="{{ $collector->user_id }}">{{ $collector->user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="assignments-sales-rep" class="label">Vendedor</label>
                <select id="assignments-sales-rep" wire:model.live="salesRepId"
                    class="input">
                    <option value="">Todos</option>
                    @foreach ($salesReps as $salesRep)
                        <option value="{{ $salesRep->id }}">{{ $salesRep->name }} · SLP {{ $salesRep->slpcode }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="assignments-active" class="label">Estado</label>
                <select id="assignments-active" wire:model.live="active"
                    class="input">
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 rounded-xl bg-canvas p-3 sm:flex-row sm:items-end">
            <div class="sm:min-w-64">
                <label for="assignments-bulk-collector" class="label">Asignar seleccionados a</label>
                <select id="assignments-bulk-collector" wire:model="bulkCollectorId"
                    class="input">
                    <option value="">Selecciona un cobrador</option>
                    @foreach ($collectors as $collector)
                        <option value="{{ $collector->user_id }}">{{ $collector->user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button" wire:click="assignSelected" wire:loading.attr="disabled"
                    class="btn btn-primary">
                    Asignar
                </button>
                <button type="button" wire:click="unassignSelected" wire:loading.attr="disabled"
                    class="btn btn-outline">
                    Quitar asignación
                </button>
            </div>

            <p class="text-sm text-muted sm:ml-auto">{{ count($selected) }} seleccionados</p>
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
                <tbody class="divide-y divide-line">
                    @forelse ($customers as $customer)
                        <tr wire:key="assignment-{{ $customer->id }}" class="transition hover:bg-brand-50/40">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model="selected" value="{{ $customer->id }}"
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

    <section class="space-y-3 md:hidden" aria-label="Asignación de clientes">
        @forelse ($customers as $customer)
            <article wire:key="assignment-card-{{ $customer->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <input type="checkbox" wire:model="selected" value="{{ $customer->id }}"
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
