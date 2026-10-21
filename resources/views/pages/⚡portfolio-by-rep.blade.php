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
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Cartera y cobranzas</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Cartera por vendedor</h1>
            <p class="mt-1.5 text-sm text-black/60">Saldo abierto agrupado por vendedor y moneda de {{ $tenant?->name ?? 'tu empresa' }}.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if ($search !== '' || $currency !== '' || $due !== '')
                <button type="button" wire:click="clearFilters"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                    Limpiar filtros
                </button>
            @endif

            <a href="{{ route('portfolio') }}"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                Volver a Cartera
            </a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Vendedores con saldo</p>
            <p class="mt-2 text-2xl font-semibold text-ink">{{ number_format($repsWithBalance, 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Facturas abiertas</p>
            <p class="mt-2 text-2xl font-semibold text-ink">{{ number_format($openInvoices, 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Saldo pendiente</p>
            <div class="mt-2 space-y-1">
                @forelse ($balances as $currencyCode => $amount)
                    <p class="text-lg font-semibold text-ink">{{ $currencyCode }} {{ $money($amount) }}</p>
                @empty
                    <p class="text-lg font-semibold text-black/60">—</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Saldo vencido</p>
            <div class="mt-2 space-y-1">
                @forelse ($overdueBalances as $currencyCode => $amount)
                    <p class="text-lg font-semibold text-rose-700">{{ $currencyCode }} {{ $money($amount) }}</p>
                @empty
                    <p class="text-lg font-semibold text-black/60">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="by-rep-search" class="mb-1.5 block text-sm font-medium text-black/70">Buscar vendedor</label>
                <input id="by-rep-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Nombre o SLP"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="by-rep-currency" class="mb-1.5 block text-sm font-medium text-black/70">Moneda</label>
                <select id="by-rep-currency" wire:model.live="currency"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todas</option>
                    @foreach ($currencies as $currencyCode)
                        <option value="{{ $currencyCode }}">{{ $currencyCode }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="by-rep-due" class="mb-1.5 block text-sm font-medium text-black/70">Vencimiento</label>
                <select id="by-rep-due" wire:model.live="due"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todos</option>
                    <option value="overdue">Vencidas</option>
                    <option value="current">Al día</option>
                </select>
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Vendedor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Moneda</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Facturas</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Clientes</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Saldo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Vencidas</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Saldo vencido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($summary as $row)
                        <tr wire:key="rep-{{ $row->sales_rep_id }}-{{ $row->currency }}" class="transition hover:bg-brand-50/40">
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $row->sales_rep_name }}</p>
                                <p class="mt-0.5 text-xs text-black/60">SLP {{ $row->slpcode }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $row->currency }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-black/70">{{ number_format((int) $row->invoice_count, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-black/70">{{ number_format((int) $row->customer_count, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-ink">{{ $money($row->balance) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm {{ (int) $row->overdue_count > 0 ? 'font-medium text-rose-700' : 'text-black/70' }}">
                                {{ number_format((int) $row->overdue_count, 0, ',', '.') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold {{ (float) $row->overdue_balance > 0 ? 'text-rose-700' : 'text-ink' }}">
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
            <article wire:key="rep-card-{{ $row->sales_rep_id }}-{{ $row->currency }}" class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $row->sales_rep_name }}</p>
                        <p class="mt-0.5 text-xs text-black/60">SLP {{ $row->slpcode }} · {{ $row->currency }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold text-ink">{{ $money($row->balance) }}</p>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-black/60">Facturas abiertas</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ number_format((int) $row->invoice_count, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Clientes</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ number_format((int) $row->customer_count, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Vencidas</dt>
                        <dd class="mt-0.5 font-semibold {{ (int) $row->overdue_count > 0 ? 'text-rose-700' : 'text-ink' }}">
                            {{ number_format((int) $row->overdue_count, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Saldo vencido</dt>
                        <dd class="mt-0.5 font-semibold {{ (float) $row->overdue_balance > 0 ? 'text-rose-700' : 'text-ink' }}">
                            {{ $money($row->overdue_balance) }}
                        </dd>
                    </div>
                </dl>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-black/15 bg-white px-4 py-10 text-center">
                <p class="text-sm font-medium text-ink">Sin saldos por vendedor con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>
</div>
