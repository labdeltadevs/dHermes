<?php

use App\Models\Invoice;
use App\Models\SalesRep;
use App\Queries\SellerPortfolioQuery;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Cartera')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $salesRepId = '';

    public string $due = '';

    public string $sort = 'due_asc';

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'salesRepId', 'due', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'salesRepId', 'due']);
        $this->sort = 'due_asc';
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $tenant = $membership->tenant;
        $filters = $this->filters($tenant);

        $query = app(SellerPortfolioQuery::class)->filtered($user, $filters);

        return $this->view()->with([
            'tenant' => $tenant,
            'membership' => $membership,
            'invoices' => $query->paginate(25)->onEachSide(1)->withQueryString(),
            'summary' => app(SellerPortfolioQuery::class)->summaryByCurrency($user, $filters),
            'salesReps' => $membership->role->canApprove()
                ? SalesRep::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slpcode'])
                : collect(),
        ]);
    }

    /**
     * @return array{search: string, sales_rep_id: string, due: string, sort: string, today: string}
     */
    private function filters($tenant): array
    {
        return [
            'search' => $this->search,
            'sales_rep_id' => $this->salesRepId,
            'due' => $this->due,
            'sort' => $this->sort,
            'today' => now($tenant?->timezone ?? config('app.timezone'))->toDateString(),
        ];
    }
};
?>

@php
$money = fn (string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Cartera y cobranzas</p>
            <h1 class="page-title">Cartera</h1>
            <p class="page-subtitle">Facturas pendientes de cobro de {{ $tenant?->name ?? 'tu empresa' }}.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if ($membership->role->canApprove())
                <a href="{{ route('portfolio.by-rep') }}" wire:navigate class="btn btn-outline">
                    Agrupado por vendedor
                </a>
            @endif

            @if ($search !== '' || $salesRepId !== '' || $due !== '')
                <button type="button" wire:click="clearFilters" class="btn btn-ghost">
                    Limpiar filtros
                </button>
            @endif
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat">
            <p class="stat-label">Facturas abiertas</p>
            <p class="stat-value">{{ number_format($summary->sum('invoice_count'), 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Saldo pendiente</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p class="text-lg font-semibold text-ink">{{ $row->currency }} {{ $money($row->balance) }}</p>
                @empty
                    <p class="text-lg font-semibold text-muted">—</p>
                @endforelse
            </div>
        </article>

        <article class="stat">
            <p class="stat-label">Vencidas</p>
            <p class="stat-value text-rose-700">{{ number_format($summary->sum('overdue_count'), 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Saldo vencido</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p class="text-lg font-semibold text-rose-700">{{ $row->currency }} {{ $money($row->overdue_balance) }}</p>
                @empty
                    <p class="text-lg font-semibold text-muted">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="card p-4 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label for="portfolio-search" class="label">Buscar</label>
                <input id="portfolio-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Cliente, código, documento"
                    class="input">
            </div>

            @if ($salesReps->isNotEmpty())
                <div>
                    <label for="portfolio-sales-rep" class="label">Vendedor</label>
                    <select id="portfolio-sales-rep" wire:model.live="salesRepId"
                        class="select">
                        <option value="">Todos</option>
                        @foreach ($salesReps as $salesRep)
                            <option value="{{ $salesRep->id }}">{{ $salesRep->name }} · SLP {{ $salesRep->slpcode }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="portfolio-due" class="label">Vencimiento</label>
                <select id="portfolio-due" wire:model.live="due"
                    class="select">
                    <option value="">Todos</option>
                    <option value="overdue">Vencidas</option>
                    <option value="due_soon">Próximas 7 días</option>
                    <option value="current">Al día</option>
                </select>
            </div>

            <div>
                <label for="portfolio-sort" class="label">Ordenar por</label>
                <select id="portfolio-sort" wire:model.live="sort"
                    class="select">
                    <option value="due_asc">Vencimiento ascendente</option>
                    <option value="due_desc">Vencimiento descendente</option>
                    <option value="balance_desc">Mayor saldo</option>
                    <option value="issue_desc">Más recientes</option>
                </select>
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden card md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head">Cliente</th>
                        <th class="table-head">Documento</th>
                        <th class="table-head">Vendedor</th>
                        <th class="table-head">Emisión</th>
                        <th class="table-head">Vencimiento</th>
                        <th class="table-head text-right">Total</th>
                        <th class="table-head text-right">Pagado</th>
                        <th class="table-head text-right">Saldo</th>
                        <th class="table-head text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($invoices as $invoice)
                        @php($canCollect = $membership->role->sells() && \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\CollectionEvent::class, $invoice]))
                        <tr wire:key="invoice-{{ $invoice->id }}" class="transition hover:bg-brand-50/40">
                            <td class="table-cell">
                                <p class="text-sm font-medium text-ink">{{ $invoice->customer?->name }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $invoice->customer?->cardcode }}</p>
                            </td>
                            <td class="table-cell">
                                <p class="text-sm font-medium text-ink">{{ $invoice->number }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $invoice->series }} · {{ $invoice->docentry }}</p>
                            </td>
                            <td class="table-cell text-muted">{{ $invoice->salesRep?->name ?? '—' }}</td>
                            <td class="table-cell whitespace-nowrap text-muted">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                            <td class="table-cell whitespace-nowrap">
                                <span class="font-medium {{ $invoice->due_date?->isBefore(now($tenant?->timezone ?? config('app.timezone'))->startOfDay()) ? 'text-rose-700' : 'text-ink' }}">
                                    {{ $invoice->due_date?->format('d/m/Y') }}
                                </span>
                            </td>
                            <td class="table-cell whitespace-nowrap text-right text-muted">{{ $invoice->currency }} {{ $money($invoice->total) }}</td>
                            <td class="table-cell whitespace-nowrap text-right text-muted">{{ $money($invoice->paid) }}</td>
                            <td class="table-cell whitespace-nowrap text-right font-semibold text-ink">{{ $money($invoice->balance) }}</td>
                            <td class="table-cell text-right">
                                @if ($canCollect)
                                    <a href="{{ route('collections.create', ['invoice' => $invoice->id]) }}"
                                        class="btn btn-primary btn-sm">
                                        Cobrar
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin facturas pendientes con estos filtros.</p>
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

    <section class="space-y-3 md:hidden" aria-label="Facturas pendientes">
        @forelse ($invoices as $invoice)
            @php($canCollect = $membership->role->sells() && \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\CollectionEvent::class, $invoice]))
            <article wire:key="invoice-card-{{ $invoice->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $invoice->customer?->name }}</p>
                        <p class="mt-0.5 text-xs text-muted">{{ $invoice->customer?->cardcode }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold text-ink">{{ $money($invoice->balance) }}</p>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">Documento</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->number }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Vendedor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $invoice->salesRep?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Vencimiento</dt>
                        <dd class="mt-0.5 font-semibold {{ $invoice->due_date?->isBefore(now($tenant?->timezone ?? config('app.timezone'))->startOfDay()) ? 'text-rose-700' : 'text-ink' }}">
                            {{ $invoice->due_date?->format('d/m/Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Total / Pagado</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $money($invoice->total) }} / {{ $money($invoice->paid) }}</dd>
                    </div>
                </dl>

                @if ($canCollect)
                    <a href="{{ route('collections.create', ['invoice' => $invoice->id]) }}"
                        class="mt-3 btn btn-primary w-full">
                        Cobrar
                    </a>
                @endif
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">Sin facturas pendientes con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>

    @if ($invoices->hasPages())
        <section class="card p-3">
            {{ $invoices->links('components.pagination') }}
        </section>
    @endif
</div>
