<?php

use App\Models\Invoice;
use App\Queries\SellerPortfolioQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Cartera por vendedor')] class extends Component
{
    public string $search = '';

    public string $currency = '';

    public string $due = '';

    public function mount(): void
    {
        $membership = auth()->user()?->currentMembership();

        abort_if($membership === null || ! $membership->role->canApprove(), 403);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'currency', 'due']);
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null || ! $membership->role->canApprove(), 403);

        $tenant = $membership->tenant;
        $today = now($tenant?->timezone ?? config('app.timezone'))->toDateString();

        $summary = app(SellerPortfolioQuery::class)->summaryBySalesRep($user, [
            'search' => $this->search,
            'currency' => $this->currency,
            'due' => $this->due,
            'today' => $today,
        ]);

        return $this->view()->with([
            'tenant' => $tenant,
            'summary' => $summary,
            'currencies' => Invoice::query()
                ->where('cancelled', false)
                ->where('balance', '>', 0)
                ->select('currency')
                ->distinct()
                ->orderBy('currency')
                ->pluck('currency'),
            'repsWithBalance' => $summary->pluck('sales_rep_id')->filter()->unique()->count(),
            'openInvoices' => (int) $summary->sum('invoice_count'),
            'balances' => $summary->groupBy('currency')->map(fn ($rows): float => (float) $rows->sum('balance')),
            'overdueBalances' => $summary->groupBy('currency')->map(fn ($rows): float => (float) $rows->sum('overdue_balance')),
        ]);
    }
};
?>

@php
$money = fn (string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-breadcrumb :items="[
                ['label' => 'Inicio', 'url' => route('dashboard')],
                ['label' => 'Cartera', 'url' => route('portfolio')],
                ['label' => 'Cartera por vendedor'],
            ]" />
            <h1 class="page-title">Cartera por vendedor</h1>
            <p class="page-subtitle">Saldo abierto agrupado por vendedor y moneda de {{ $tenant?->name ?? 'tu empresa' }}.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if ($search !== '' || $currency !== '' || $due !== '')
                <button type="button" wire:click="clearFilters"
                    class="btn btn-outline">
                    Limpiar filtros
                </button>
            @endif

            <a href="{{ route('portfolio') }}"
                class="btn btn-outline">
                Volver a Cartera
            </a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat">
            <p class="stat-label">Vendedores con saldo</p>
            <p class="stat-value">{{ number_format($repsWithBalance, 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Facturas abiertas</p>
            <p class="stat-value">{{ number_format($openInvoices, 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Saldo pendiente</p>
            <div class="mt-2 space-y-1">
                @forelse ($balances as $currencyCode => $amount)
                    <p class="text-lg font-semibold text-ink">{{ $currencyCode }} {{ $money($amount) }}</p>
                @empty
                    <p class="text-lg font-semibold text-muted">—</p>
                @endforelse
            </div>
        </article>

        <article class="stat">
            <p class="stat-label">Saldo vencido</p>
            <div class="mt-2 space-y-1">
                @forelse ($overdueBalances as $currencyCode => $amount)
                    <p class="text-lg font-semibold text-rose-700">{{ $currencyCode }} {{ $money($amount) }}</p>
                @empty
                    <p class="text-lg font-semibold text-muted">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="card p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="by-rep-search" class="label">Buscar vendedor</label>
                <input id="by-rep-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Nombre o SLP"
                    class="input">
            </div>

            <div>
                <label for="by-rep-currency" class="label">Moneda</label>
                <select id="by-rep-currency" wire:model.live="currency"
                    class="select">
                    <option value="">Todas</option>
                    @foreach ($currencies as $currencyCode)
                        <option value="{{ $currencyCode }}">{{ $currencyCode }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="by-rep-due" class="label">Vencimiento</label>
                <select id="by-rep-due" wire:model.live="due"
                    class="select">
                    <option value="">Todos</option>
                    <option value="overdue">Vencidas</option>
                    <option value="current">Al día</option>
                </select>
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden card md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head">Vendedor</th>
                        <th class="table-head">Moneda</th>
                        <th class="table-head text-right">Facturas</th>
                        <th class="table-head text-right">Clientes</th>
                        <th class="table-head text-right">Saldo</th>
                        <th class="table-head text-right">Vencidas</th>
                        <th class="table-head text-right">Saldo vencido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($summary as $row)
                        <tr wire:key="rep-{{ $row->sales_rep_id }}-{{ $row->currency }}" class="transition hover:bg-brand-50/40">
                            <td class="table-cell">
                                <p class="text-sm font-medium text-ink">{{ $row->sales_rep_name }}</p>
                                <p class="mt-0.5 text-xs text-muted">SLP {{ $row->slpcode }}</p>
                            </td>
                            <td class="table-cell text-muted">{{ $row->currency }}</td>
                            <td class="table-cell whitespace-nowrap text-right text-muted">{{ number_format((int) $row->invoice_count, 0, ',', '.') }}</td>
                            <td class="table-cell whitespace-nowrap text-right text-muted">{{ number_format((int) $row->customer_count, 0, ',', '.') }}</td>
                            <td class="table-cell whitespace-nowrap text-right font-semibold text-ink">{{ $money($row->balance) }}</td>
                            <td class="table-cell whitespace-nowrap text-right {{ (int) $row->overdue_count > 0 ? 'font-medium text-rose-700' : 'text-muted' }}">
                                {{ number_format((int) $row->overdue_count, 0, ',', '.') }}
                            </td>
                            <td class="table-cell whitespace-nowrap text-right font-semibold {{ (float) $row->overdue_balance > 0 ? 'text-rose-700' : 'text-ink' }}">
                                {{ $money($row->overdue_balance) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin saldos por vendedor con estos filtros.</p>
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

    <section class="space-y-3 md:hidden" aria-label="Cartera por vendedor">
        @forelse ($summary as $row)
            <article wire:key="rep-card-{{ $row->sales_rep_id }}-{{ $row->currency }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $row->sales_rep_name }}</p>
                        <p class="mt-0.5 text-xs text-muted">SLP {{ $row->slpcode }} · {{ $row->currency }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold text-ink">{{ $money($row->balance) }}</p>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">Facturas abiertas</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ number_format((int) $row->invoice_count, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Clientes</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ number_format((int) $row->customer_count, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Vencidas</dt>
                        <dd class="mt-0.5 font-semibold {{ (int) $row->overdue_count > 0 ? 'text-rose-700' : 'text-ink' }}">
                            {{ number_format((int) $row->overdue_count, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Saldo vencido</dt>
                        <dd class="mt-0.5 font-semibold {{ (float) $row->overdue_balance > 0 ? 'text-rose-700' : 'text-ink' }}">
                            {{ $money($row->overdue_balance) }}
                        </dd>
                    </div>
                </dl>
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">Sin saldos por vendedor con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>
</div>
