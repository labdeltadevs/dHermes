<?php

use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\TenantUser;
use App\Models\User;
use App\Queries\CollectionQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Reporte de cobranzas')] class extends Component {
    public string $from = '';

    public string $to = '';

    public string $paymentMethod = '';

    public string $status = CollectionStatus::Accepted->value;

    public string $sellerId = '';

    public string $currency = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CollectionEvent::class);

        $this->from = now()->toDateString();
        $this->to = now()->toDateString();
    }

    public function applyToday(): void
    {
        $this->from = now()->toDateString();
        $this->to = now()->toDateString();
    }

    public function applyYesterday(): void
    {
        $this->from = now()->subDay()->toDateString();
        $this->to = now()->subDay()->toDateString();
    }

    public function clearFilters(): void
    {
        $this->reset(['paymentMethod', 'sellerId', 'currency']);
        $this->status = CollectionStatus::Accepted->value;
        $this->applyToday();
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = app(CollectionQuery::class);
        $filters = $this->filters();
        $events = $query->filtered($user, $filters)->get();

        $groups = collect(PaymentMethod::cases())
            ->map(fn (PaymentMethod $method): array => [
                'method' => $method,
                'events' => $events->where('payment_method', $method)->values(),
            ])
            ->filter(fn (array $group): bool => $group['events']->isNotEmpty())
            ->values();

        $exportUrl = route('collections.report.csv', array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'payment_method' => $filters['payment_method'],
            'seller_user_id' => $filters['seller_user_id'],
            'currency' => $filters['currency'],
        ], fn (string $value): bool => $value !== ''));

        $exportPdfUrl = route('collections.report.pdf', array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'payment_method' => $filters['payment_method'],
            'seller_user_id' => $filters['seller_user_id'],
            'currency' => $filters['currency'],
        ], fn (string $value): bool => $value !== ''));

        return $this->view()->with([
            'groups' => $groups,
            'totalCount' => $events->count(),
            'totalsByCurrency' => $events->groupBy('currency')->map(
                fn ($group, string $currency): array => [
                    'currency' => $currency,
                    'total' => (float) $group->sum(fn (CollectionEvent $event): float => (float) $event->amount),
                    'count' => $group->count(),
                ],
            )->values(),
            'sellers' => $membership->role->canApprove()
                ? TenantUser::query()
                    ->with('user:id,name')
                    ->whereIn('role', [TenantRole::Seller, TenantRole::Collector])
                    ->where('active', true)
                    ->get()
                    ->map(fn (TenantUser $membership): ?User => $membership->user)
                    ->filter()
                    ->unique('id')
                    ->sortBy('name')
                    ->values()
                : collect(),
            'currencies' => $query->for($user)->reorder()->select('currency')->distinct()->orderBy('currency')->pluck('currency'),
            'isApprover' => $membership->role->canApprove(),
            'statuses' => CollectionStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
            'exportUrl' => $exportUrl,
            'exportPdfUrl' => $exportPdfUrl,
        ]);
    }

    /**
     * @return array{from: string, to: string, status: string, payment_method: string, seller_user_id: string, currency: string}
     */
    private function filters(): array
    {
        return [
            'from' => $this->isDate($this->from) ? $this->from : now()->toDateString(),
            'to' => $this->isDate($this->to) ? $this->to : now()->toDateString(),
            'status' => $this->status,
            'payment_method' => $this->paymentMethod,
            'seller_user_id' => $this->sellerId,
            'currency' => $this->currency,
        ];
    }

    private function isDate(string $value): bool
    {
        return $value !== '' && Carbon::hasFormat($value, 'Y-m-d');
    }
};
?>

@php
    $money = fn(string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-breadcrumb :items="[
                ['label' => 'Inicio', 'url' => route('dashboard')],
                ['label' => 'Cobranzas', 'url' => route('collections.index')],
                ['label' => 'Reportes'],
            ]" />
            <h1 class="page-title">Reporte de cobranzas</h1>
            <p class="page-subtitle">
                Cobros por periodo, separados por método de pago, con la factura relacionada y el monto asignado.
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <a href="{{ $exportPdfUrl }}" class="btn btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                Descargar PDF
            </a>

            <a href="{{ $exportUrl }}" class="btn btn-outline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Exportar CSV
            </a>

            <button type="button" wire:click="clearFilters" class="btn btn-outline">
                Limpiar filtros
            </button>
        </div>
    </section>

    <section class="card p-4 sm:p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="report-from" class="label">Desde</label>
                <input id="report-from" type="date" wire:model.live="from" class="input">
            </div>

            <div>
                <label for="report-to" class="label">Hasta</label>
                <input id="report-to" type="date" wire:model.live="to" class="input">
            </div>

            <div>
                <label for="report-method" class="label">Método de pago</label>
                <select id="report-method" wire:model.live="paymentMethod" class="select">
                    <option value="">Todos</option>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="report-status" class="label">Estado</label>
                <select id="report-status" wire:model.live="status" class="select">
                    <option value="">Todos</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>

            @if ($isApprover)
                <div>
                    <label for="report-seller" class="label">Vendedor</label>
                    <select id="report-seller" wire:model.live="sellerId" class="select">
                        <option value="">Todos</option>
                        @foreach ($sellers as $seller)
                            <option value="{{ $seller->id }}">{{ $seller->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="report-currency" class="label">Moneda</label>
                <select id="report-currency" wire:model.live="currency" class="select">
                    <option value="">Todas</option>
                    @foreach ($currencies as $currencyOption)
                        <option value="{{ $currencyOption }}">{{ $currencyOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                <button type="button" wire:click="applyToday" class="btn btn-outline">Hoy</button>
                <button type="button" wire:click="applyYesterday" class="btn btn-outline">Ayer</button>
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat">
            <p class="stat-label">Cobros</p>
            <p class="stat-value">{{ number_format($totalCount, 0, ',', '.') }}</p>
        </article>

        @foreach ($totalsByCurrency as $total)
            <article class="stat">
                <p class="stat-label">Total recibido {{ $total['currency'] }}</p>
                <p class="stat-value">{{ $money($total['total']) }}</p>
                <p class="mt-1 text-xs text-muted">{{ number_format($total['count'], 0, ',', '.') }} cobros</p>
            </article>
        @endforeach
    </section>

    @if ($totalsByCurrency->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-ink/10 bg-ink px-5 py-4 text-white shadow-pop">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-white/60">Total general de pagos recibidos</p>
                    <p class="mt-1 text-sm text-white/70">{{ number_format($totalCount, 0, ',', '.') }} cobros en el periodo y filtros seleccionados</p>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    @foreach ($totalsByCurrency as $total)
                        <div class="text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-white/60">{{ $total['currency'] }}</p>
                            <p class="text-2xl font-semibold tabular-nums tracking-tight">{{ $money($total['total']) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @forelse ($groups as $group)
        <section class="space-y-3" wire:key="report-group-{{ $group['method']->value }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-ink">{{ $group['method']->label() }}</h2>
                    <span class="badge bg-black/[0.04] text-muted">{{ $group['events']->count() }} cobros</span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($group['events']->groupBy('currency') as $currencyKey => $currencyEvents)
                        <span class="badge bg-black/[0.04] text-ink">
                            {{ $currencyKey }} {{ $money($currencyEvents->sum(fn($event): float => (float) $event->amount)) }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-black/[0.07] bg-white shadow-[0_1px_2px_rgb(0_0_0/0.05)]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] border-collapse text-[13px] leading-5">
                        <thead>
                            <tr class="border-b border-black/[0.07] bg-[#FAFAF8] text-left">
                                <th class="whitespace-nowrap px-4 py-3 pl-5 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Identificador app</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Identificador interno</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Factura (NumAtCard)</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Monto asignado</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Fecha / hora creación</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Código cliente</th>
                                <th class="whitespace-nowrap px-4 py-3 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Cliente</th>
                                <th class="whitespace-nowrap px-4 py-3 pr-5 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-black/45">
                                    Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.06]">
                            @foreach ($group['events'] as $event)
                                <tr wire:key="report-row-{{ $event->id }}" class="hover:bg-brand-50/40">
                                    <td class="whitespace-nowrap px-4 py-3 pl-5 align-middle">
                                        <code class="rounded-md bg-black/[0.05] px-1.5 py-0.5 font-mono text-[11px] font-medium text-ink">{{ $event->number_code ?? '—' }}</code>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle">
                                        <span class="font-mono text-[11px] text-black/45" title="{{ $event->id }}">{{ substr($event->id, 0, 8) }}…</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle font-semibold tabular-nums text-ink">
                                        {{ $event->invoice?->number ?? $event->flash_invoice_number ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-middle font-semibold tabular-nums text-ink">
                                        {{ $event->currency }} {{ $money($event->amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle tabular-nums text-black/60">
                                        {{ $event->created_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle font-mono text-[11px] text-black/60">
                                        {{ $event->invoice?->customer?->cardcode ?? $event->flash_cardcode ?? '—' }}
                                    </td>
                                    <td class="max-w-[220px] truncate px-4 py-3 align-middle text-ink">
                                        {{ $event->invoice?->customer?->name ?? $event->flash_customer_name ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 pr-5 align-middle">
                                        <span class="badge bg-black/[0.04] text-muted">{{ $event->status->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @empty
        <div class="empty-state">
            <p class="text-sm font-medium text-ink">Sin cobros para este periodo y filtros.</p>
            <p class="mt-1 text-sm text-muted">Ajusta las fechas, el método de pago o el estado.</p>
        </div>
    @endforelse
</div>
