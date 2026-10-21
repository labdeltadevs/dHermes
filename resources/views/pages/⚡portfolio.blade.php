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
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Cartera y cobranzas</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Cartera</h1>
            <p class="mt-1.5 text-sm text-black/60">Facturas pendientes de cobro de {{ $tenant?->name ?? 'tu empresa' }}.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if ($membership->role->canApprove())
                <a href="{{ route('portfolio.by-rep') }}"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                    Agrupado por vendedor
                </a>
            @endif

            @if ($search !== '' || $salesRepId !== '' || $due !== '')
                <button type="button" wire:click="clearFilters"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                    Limpiar filtros
                </button>
            @endif
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Facturas abiertas</p>
            <p class="mt-2 text-2xl font-semibold text-ink">{{ number_format($summary->sum('invoice_count'), 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Saldo pendiente</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p class="text-lg font-semibold text-ink">{{ $row->currency }} {{ $money($row->balance) }}</p>
                @empty
                    <p class="text-lg font-semibold text-black/60">—</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Vencidas</p>
            <p class="mt-2 text-2xl font-semibold text-rose-700">{{ number_format($summary->sum('overdue_count'), 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Saldo vencido</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p class="text-lg font-semibold text-rose-700">{{ $row->currency }} {{ $money($row->overdue_balance) }}</p>
                @empty
                    <p class="text-lg font-semibold text-black/60">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label for="portfolio-search" class="mb-1.5 block text-sm font-medium text-black/70">Buscar</label>
                <input id="portfolio-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Cliente, código, documento"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            @if ($salesReps->isNotEmpty())
                <div>
                    <label for="portfolio-sales-rep" class="mb-1.5 block text-sm font-medium text-black/70">Vendedor</label>
                    <select id="portfolio-sales-rep" wire:model.live="salesRepId"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        <option value="">Todos</option>
                        @foreach ($salesReps as $salesRep)
                            <option value="{{ $salesRep->id }}">{{ $salesRep->name }} · SLP {{ $salesRep->slpcode }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="portfolio-due" class="mb-1.5 block text-sm font-medium text-black/70">Vencimiento</label>
                <select id="portfolio-due" wire:model.live="due"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todos</option>
                    <option value="overdue">Vencidas</option>
                    <option value="due_soon">Próximas 7 días</option>
                    <option value="current">Al día</option>
                </select>
            </div>

            <div>
                <label for="portfolio-sort" class="mb-1.5 block text-sm font-medium text-black/70">Ordenar por</label>
                <select id="portfolio-sort" wire:model.live="sort"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="due_asc">Vencimiento ascendente</option>
                    <option value="due_desc">Vencimiento descendente</option>
                    <option value="balance_desc">Mayor saldo</option>
                    <option value="issue_desc">Más recientes</option>
                </select>
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Documento</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Vendedor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Emisión</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Vencimiento</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Pagado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Saldo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($invoices as $invoice)
                        @php($canCollect = $membership->role->sells() && \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\CollectionEvent::class, $invoice]))
                        <tr wire:key="invoice-{{ $invoice->id }}" class="transition hover:bg-brand-50/40">
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $invoice->customer?->name }}</p>
                                <p class="mt-0.5 text-xs text-black/60">{{ $invoice->customer?->cardcode }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $invoice->number }}</p>
                                <p class="mt-0.5 text-xs text-black/60">{{ $invoice->series }} · {{ $invoice->docentry }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-black/70">{{ $invoice->salesRep?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm">
                                <span class="font-medium {{ $invoice->due_date?->isBefore(now($tenant?->timezone ?? config('app.timezone'))->startOfDay()) ? 'text-rose-700' : 'text-ink' }}">
                                    {{ $invoice->due_date?->format('d/m/Y') }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-black/70">{{ $invoice->currency }} {{ $money($invoice->total) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-black/70">{{ $money($invoice->paid) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-ink">{{ $money($invoice->balance) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                @if ($canCollect)
                                    <a href="{{ route('collections.create', ['invoice' => $invoice->id]) }}"
                                        class="inline-flex min-h-9 items-center justify-center rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-bold text-ink transition hover:bg-brand-400">
                                        Cobrar
                                    </a>
                                @else
                                    <span class="text-black/30">—</span>
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
            <article wire:key="invoice-card-{{ $invoice->id }}" class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $invoice->customer?->name }}</p>
                        <p class="mt-0.5 text-xs text-black/60">{{ $invoice->customer?->cardcode }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold text-ink">{{ $money($invoice->balance) }}</p>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-black/60">Documento</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->number }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Vendedor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $invoice->salesRep?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Vencimiento</dt>
                        <dd class="mt-0.5 font-semibold {{ $invoice->due_date?->isBefore(now($tenant?->timezone ?? config('app.timezone'))->startOfDay()) ? 'text-rose-700' : 'text-ink' }}">
                            {{ $invoice->due_date?->format('d/m/Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Total / Pagado</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $money($invoice->total) }} / {{ $money($invoice->paid) }}</dd>
                    </div>
                </dl>

                @if ($canCollect)
                    <a href="{{ route('collections.create', ['invoice' => $invoice->id]) }}"
                        class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-3 py-2 text-sm font-bold text-ink transition hover:bg-brand-400">
                        Cobrar
                    </a>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-black/15 bg-white px-4 py-10 text-center">
                <p class="text-sm font-medium text-ink">Sin facturas pendientes con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>

    @if ($invoices->hasPages())
        <section class="rounded-2xl border border-black/10 bg-white p-3 shadow-sm">
            {{ $invoices->links('components.pagination') }}
        </section>
    @endif
</div>
