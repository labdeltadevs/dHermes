<?php

use App\Enums\TenantRole;
use App\Models\Receipt;
use App\Models\SalesRep;
use App\Queries\ReceiptQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Cobros')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $salesRepId = '';

    public string $from = '';

    public string $to = '';

    public string $sort = 'receipt_date_desc';

    public function mount(): void
    {
        Gate::authorize('viewAny', Receipt::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'salesRepId', 'from', 'to', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'salesRepId', 'from', 'to']);
        $this->sort = 'receipt_date_desc';
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $filters = $this->filters();
        $query = app(ReceiptQuery::class)->filtered($user, $filters);
        $isSeller = $membership->role === TenantRole::Seller;

        return $this->view()->with([
            'receipts' => $query->paginate(25)->onEachSide(1)->withQueryString(),
            'summary' => app(ReceiptQuery::class)->summaryByCurrency($user, $filters),
            'salesReps' => $membership->role->canApprove()
                ? SalesRep::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'slpcode'])
                : collect(),
            'isSeller' => $isSeller,
            'sellerName' => $isSeller ? ($membership->salesRep?->name ?? $user->name) : null,
            'sellerSlpcode' => $isSeller ? $membership->salesRep?->slpcode : null,
        ]);
    }

    /**
     * @return array{search: string, sales_rep_id: string, from: string, to: string, sort: string}
     */
    private function filters(): array
    {
        return [
            'search' => $this->search,
            'sales_rep_id' => $this->salesRepId,
            'from' => $this->isDate($this->from) ? $this->from : '',
            'to' => $this->isDate($this->to) ? $this->to : '',
            'sort' => $this->sort,
        ];
    }

    private function isDate(string $value): bool
    {
        return $value !== '' && Carbon::hasFormat($value, 'Y-m-d');
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
            <h1 class="page-title">Cobros</h1>
            <p class="page-subtitle">Comprobantes de cobro registrados en SAP.</p>
        </div>

        @if ($search !== '' || $salesRepId !== '' || $from !== '' || $to !== '')
            <button type="button" wire:click="clearFilters"
                class="btn btn-outline">
                Limpiar filtros
            </button>
        @endif
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat">
            <p class="stat-label">Comprobantes</p>
            <p class="stat-value">{{ number_format($summary->sum('receipts_count'), 0, ',', '.') }}</p>
        </article>

        <article class="stat xl:col-span-3">
            <p class="stat-label">{{ $isSeller ? 'Aplicado a mi cartera' : 'Monto cobrado' }}</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p class="text-2xl font-semibold text-ink">{{ $row->currency }} {{ $money($row->total) }}</p>
                @empty
                    <p class="text-2xl font-semibold text-muted">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="card p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label for="receipts-search" class="label">Buscar</label>
                <input id="receipts-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Cliente, código, comprobante"
                    class="input">
            </div>

            @if ($salesReps->isNotEmpty())
                <div>
                    <label for="receipts-sales-rep" class="label">Vendedor</label>
                    <select id="receipts-sales-rep" wire:model.live="salesRepId"
                        class="select">
                        <option value="">Todos</option>
                        @foreach ($salesReps as $salesRep)
                            <option value="{{ $salesRep->id }}">{{ $salesRep->name }} · SLP {{ $salesRep->slpcode }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="receipts-from" class="label">Desde</label>
                <input id="receipts-from" type="date" wire:model.live="from"
                    class="input">
            </div>

            <div>
                <label for="receipts-to" class="label">Hasta</label>
                <input id="receipts-to" type="date" wire:model.live="to"
                    class="input">
            </div>

            <div>
                <label for="receipts-sort" class="label">Ordenar por</label>
                <select id="receipts-sort" wire:model.live="sort"
                    class="select">
                    <option value="receipt_date_desc">Fecha descendente</option>
                    <option value="receipt_date_asc">Fecha ascendente</option>
                    <option value="total_desc">Mayor monto</option>
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
                        <th class="table-head">Comprobante</th>
                        <th class="table-head">Vendedor</th>
                        <th class="table-head">Fecha</th>
                        <th class="table-head">Moneda</th>
                        <th class="table-head text-right">{{ $isSeller ? 'Aplicado a mi cartera' : 'Total' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($receipts as $receipt)
                        <tr wire:key="receipt-{{ $receipt->id }}" class="transition hover:bg-brand-50/40">
                            <td class="table-cell">
                                <p class="text-sm font-medium text-ink">{{ $receipt->customer?->name }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $receipt->customer?->cardcode }}</p>
                            </td>
                            <td class="table-cell">
                                <p class="text-sm font-medium text-ink">{{ $receipt->number }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $receipt->docentry }}</p>
                            </td>
                            <td class="table-cell text-muted">
                                @if ($isSeller)
                                    <p>{{ $sellerName ?? '—' }}</p>
                                    @if ($sellerSlpcode)
                                        <p class="mt-0.5 text-xs text-muted">SLP {{ $sellerSlpcode }}</p>
                                    @endif
                                @else
                                    <p>{{ $receipt->customer?->salesRep?->name ?? '—' }}</p>
                                    @if ($receipt->customer?->salesRep?->slpcode)
                                        <p class="mt-0.5 text-xs text-muted">SLP {{ $receipt->customer->salesRep->slpcode }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="table-cell whitespace-nowrap text-muted">{{ $receipt->receipt_date?->format('d/m/Y') }}</td>
                            <td class="table-cell text-muted">{{ $receipt->currency }}</td>
                            <td class="table-cell whitespace-nowrap text-right font-semibold text-ink">{{ $money($receipt->attributed_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin comprobantes con estos filtros.</p>
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

    <section class="space-y-3 md:hidden" aria-label="Comprobantes de cobro">
        @forelse ($receipts as $receipt)
            <article wire:key="receipt-card-{{ $receipt->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $receipt->customer?->name }}</p>
                        <p class="mt-0.5 text-xs text-muted">{{ $receipt->customer?->cardcode }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted">{{ $isSeller ? 'Aplicado' : 'Total' }}</p>
                        <p class="text-sm font-semibold text-ink">{{ $money($receipt->attributed_total) }}</p>
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">Comprobante</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $receipt->number }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Vendedor</dt>
                        <dd class="mt-0.5 font-semibold text-ink">
                            {{ $isSeller ? ($sellerName ?? '—') : ($receipt->customer?->salesRep?->name ?? '—') }}
                            @if ($isSeller ? $sellerSlpcode : $receipt->customer?->salesRep?->slpcode)
                                <span class="block text-xs font-normal text-muted">SLP {{ $isSeller ? $sellerSlpcode : $receipt->customer->salesRep->slpcode }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Fecha</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $receipt->receipt_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Moneda</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $receipt->currency }}</dd>
                    </div>
                </dl>
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">Sin comprobantes con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>

    @if ($receipts->hasPages())
        <section class="card p-3">
            {{ $receipts->links('components.pagination') }}
        </section>
    @endif
</div>
