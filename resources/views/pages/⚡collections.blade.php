<?php

use App\Actions\Collection\MatchCollection;
use App\Enums\CollectionStatus;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\Receipt;
use App\Models\TenantUser;
use App\Models\User;
use App\Queries\CollectionQuery;
use App\Support\Verification\IssueVerificationCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Cobranzas')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sellerId = '';

    public string $currency = '';

    public string $from = '';

    public string $to = '';

    public string $matchEventId = '';

    public string $receiptId = '';

    public string $discrepancyReason = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CollectionEvent::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'sellerId', 'currency', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'sellerId', 'currency', 'from', 'to']);
        $this->resetPage();
    }

    public function openMatchPanel(string $eventId): void
    {
        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->findOrFail($eventId);

        Gate::authorize('match', $event);

        if ($event->status !== CollectionStatus::Pending) {
            $this->addError('status', 'Solo se pueden conciliar cobranzas pendientes.');

            return;
        }

        $this->matchEventId = $event->id;
        $this->receiptId = '';
        $this->discrepancyReason = '';
        $this->resetValidation();
    }

    public function closeMatchPanel(): void
    {
        $this->reset(['matchEventId', 'receiptId', 'discrepancyReason']);
        $this->resetValidation();
    }

    public function issueComprobante(string $eventId): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $event = app(CollectionQuery::class)->for($user)->findOrFail($eventId);

        abort_unless($event->status === CollectionStatus::Matched, 403);

        $code = app(IssueVerificationCode::class)->handle($user, $event);

        $this->redirect(route('verification.print', $code->code));
    }

    public function matchReceipt(): void
    {
        $this->validate([
            'matchEventId' => ['required', 'string'],
            'receiptId' => ['required', 'string'],
        ], [
            'receiptId.required' => 'Selecciona un comprobante para conciliar.',
        ]);

        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->findOrFail($this->matchEventId);
        $receipt = Receipt::query()->findOrFail($this->receiptId);

        app(MatchCollection::class)->handle($user, $event, $receipt);

        session()->flash('status', 'Cobranza conciliada correctamente.');
        $this->closeMatchPanel();
    }

    public function markDiscrepancy(): void
    {
        $this->validate([
            'matchEventId' => ['required', 'string'],
            'discrepancyReason' => ['required', 'string', 'max:1000'],
        ], [
            'discrepancyReason.required' => 'El motivo de la discrepancia es obligatorio.',
        ]);

        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->findOrFail($this->matchEventId);

        app(MatchCollection::class)->markDiscrepancy($user, $event, $this->discrepancyReason);

        session()->flash('status', 'Cobranza marcada como discrepancia.');
        $this->closeMatchPanel();
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = app(CollectionQuery::class);
        $filters = $this->filters();
        $isApprover = $membership->role->canApprove();
        $selectedEvent = $this->matchEventId !== ''
            ? $query->for($user)->find($this->matchEventId)
            : null;

        return $this->view()->with([
            'events' => $query->filtered($user, $filters)->paginate(25)->onEachSide(1)->withQueryString(),
            'summary' => $query->summaryByCurrency($user, $filters),
            'isApprover' => $isApprover,
            'selectedEvent' => $selectedEvent,
            'candidates' => $selectedEvent instanceof CollectionEvent
                ? $query->candidatesFor($selectedEvent)->get()
                : collect(),
            'sellers' => $isApprover
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
            'currencies' => $query->for($user)
                ->reorder()
                ->select('currency')
                ->distinct()
                ->orderBy('currency')
                ->pluck('currency'),
        ]);
    }

    /**
     * @return array{search: string, status: string, seller_user_id: string, currency: string, from: string, to: string}
     */
    private function filters(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'seller_user_id' => $this->sellerId,
            'currency' => $this->currency,
            'from' => $this->isDate($this->from) ? $this->from : '',
            'to' => $this->isDate($this->to) ? $this->to : '',
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
$statusLabels = [
    'pending' => 'Pendiente',
    'matched' => 'Conciliado',
    'discrepancy' => 'Discrepancia',
];
$statusClasses = [
    'pending' => 'bg-amber-100 text-amber-800',
    'matched' => 'bg-emerald-100 text-emerald-700',
    'discrepancy' => 'bg-rose-100 text-rose-700',
];
$paymentLabels = [
    'cash' => 'Efectivo',
    'check' => 'Cheque',
    'transfer' => 'Transferencia',
    'other' => 'Otro',
];
$kindLabels = [
    'full' => 'Total',
    'partial' => 'Parcial',
];
$hasFilters = $search !== '' || $status !== '' || $sellerId !== '' || $currency !== '' || $from !== '' || $to !== '';
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Cartera y cobranzas</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Cobranzas</h1>
            <p class="mt-1.5 text-sm text-black/60">Registro y conciliación de cobros informados por los vendedores.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($hasFilters)
                <button type="button" wire:click="clearFilters"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                    Limpiar filtros
                </button>
            @endif

            <a href="{{ route('collections.create') }}" wire:navigate
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-brand-400">
                Nuevo cobro
            </a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Pendientes</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format($summary->sum('pending_count'), 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Conciliados</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary->sum('matched_count'), 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Discrepancias</p>
            <p class="mt-2 text-2xl font-semibold text-rose-700">{{ number_format($summary->sum('discrepancy_count'), 0, ',', '.') }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Total cobrado</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p wire:key="summary-{{ $row->currency }}" class="text-lg font-semibold text-ink">{{ $row->currency }} {{ $money($row->total) }}</p>
                @empty
                    <p class="text-lg font-semibold text-black/60">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="collections-search" class="mb-1.5 block text-sm font-medium text-black/70">Buscar</label>
                <input id="collections-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Factura, cliente, código"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="collections-status" class="mb-1.5 block text-sm font-medium text-black/70">Estado</label>
                <select id="collections-status" wire:model.live="status"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="matched">Conciliado</option>
                    <option value="discrepancy">Discrepancia</option>
                </select>
            </div>

            @if ($sellers->isNotEmpty())
                <div>
                    <label for="collections-seller" class="mb-1.5 block text-sm font-medium text-black/70">Vendedor</label>
                    <select id="collections-seller" wire:model.live="sellerId"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        <option value="">Todos</option>
                        @foreach ($sellers as $seller)
                            <option wire:key="seller-option-{{ $seller->id }}" value="{{ $seller->id }}">{{ $seller->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="collections-currency" class="mb-1.5 block text-sm font-medium text-black/70">Moneda</label>
                <select id="collections-currency" wire:model.live="currency"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todas</option>
                    @foreach ($currencies as $currencyOption)
                        <option wire:key="currency-option-{{ $currencyOption }}" value="{{ $currencyOption }}">{{ $currencyOption }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="collections-from" class="mb-1.5 block text-sm font-medium text-black/70">Desde</label>
                <input id="collections-from" type="date" wire:model.live="from"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="collections-to" class="mb-1.5 block text-sm font-medium text-black/70">Hasta</label>
                <input id="collections-to" type="date" wire:model.live="to"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>
        </div>
    </section>

    @if ($errors->has('status'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first('status') }}
        </div>
    @endif

    @if ($selectedEvent instanceof CollectionEvent)
        <section class="rounded-2xl border border-brand-200 bg-brand-50/50 p-5 shadow-sm sm:p-6" aria-labelledby="match-panel-title">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="match-panel-title" class="text-base font-semibold text-ink">Conciliar cobranza</h2>
                    <p class="mt-1 text-sm text-black/60">
                        {{ $selectedEvent->invoice?->number }} · {{ $selectedEvent->invoice?->customer?->name }} ·
                        {{ $selectedEvent->currency }} {{ $money($selectedEvent->amount) }}
                    </p>
                </div>

                <button type="button" wire:click="closeMatchPanel"
                    class="text-sm font-medium text-black/60 transition hover:text-ink">
                    Cerrar
                </button>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-black/10 bg-white p-4">
                    <label for="match-receipt" class="mb-1.5 block text-sm font-medium text-black/70">Comprobante candidato</label>
                    <select id="match-receipt" wire:model="receiptId"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        <option value="">Selecciona un comprobante</option>
                        @foreach ($candidates as $candidate)
                            <option wire:key="candidate-option-{{ $candidate->id }}" value="{{ $candidate->id }}">
                                {{ $candidate->number }} · {{ $candidate->receipt_date?->format('d/m/Y') }} · {{ $candidate->currency }} {{ $money($candidate->total) }}
                            </option>
                        @endforeach
                    </select>

                    @error('receiptId')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    @if ($candidates->isEmpty())
                        <p class="mt-2 text-sm text-black/60">No hay comprobantes aplicados a esta factura con la misma moneda.</p>
                    @endif

                    <button type="button" wire:click="matchReceipt" wire:loading.attr="disabled"
                        class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50">
                        Conciliar comprobante
                    </button>
                </div>

                <div class="rounded-xl border border-black/10 bg-white p-4">
                    <label for="match-reason" class="mb-1.5 block text-sm font-medium text-black/70">Motivo de discrepancia</label>
                    <textarea id="match-reason" wire:model="discrepancyReason" rows="4"
                        placeholder="Describe por qué no se puede conciliar"
                        class="block w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm"></textarea>

                    @error('discrepancyReason')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    <button type="button" wire:click="markDiscrepancy" wire:loading.attr="disabled"
                        class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50">
                        Marcar discrepancia
                    </button>
                </div>
            </div>
        </section>
    @endif

    <section class="hidden overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Factura</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Vendedor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Método</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Moneda / Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Comprobante</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($events as $event)
                        <tr wire:key="collection-{{ $event->id }}" class="transition hover:bg-brand-50/40">
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $event->invoice?->number }}</p>
                                <p class="mt-0.5 text-xs text-black/60">{{ $event->invoice?->docentry }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $event->invoice?->customer?->name }}</p>
                                <p class="mt-0.5 text-xs text-black/60">{{ $event->invoice?->customer?->cardcode }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-black/70">{{ $event->seller?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $event->collected_on?->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $paymentLabels[$event->payment_method->value] ?? $event->payment_method->value }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <p class="text-sm font-semibold text-ink">{{ $event->currency }} {{ $money($event->amount) }}</p>
                                <p class="mt-0.5 text-xs text-black/60">{{ $kindLabels[$event->kind->value] ?? $event->kind->value }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses[$event->status->value] ?? 'bg-black/[0.04] text-black/60' }}">
                                    {{ $statusLabels[$event->status->value] ?? $event->status->value }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-black/70">{{ $event->matchedReceipt?->number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                @if ($isApprover && $event->status === CollectionStatus::Pending)
                                    <button type="button" wire:click="openMatchPanel('{{ $event->id }}')"
                                        class="font-medium text-brand-700 transition hover:text-brand-800">
                                        Conciliar
                                    </button>
                                @endif

                                @if ($event->status === CollectionStatus::Matched)
                                    <button type="button" wire:click="issueComprobante('{{ $event->id }}')"
                                        class="font-medium text-brand-700 transition hover:text-brand-800">
                                        Comprobante
                                    </button>
                                @endif

                                @if ($event->status === CollectionStatus::Discrepancy || (! $isApprover && $event->status === CollectionStatus::Pending))
                                    <span class="text-black/40">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin cobranzas con estos filtros.</p>
                                @if ($hasFilters)
                                    <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                                        Limpiar filtros
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Cobranzas registradas">
        @forelse ($events as $event)
            <article wire:key="collection-card-{{ $event->id }}" class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $event->invoice?->customer?->name }}</p>
                        <p class="mt-0.5 text-xs text-black/60">{{ $event->invoice?->number }} · {{ $event->invoice?->customer?->cardcode }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-ink">{{ $event->currency }} {{ $money($event->amount) }}</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $statusClasses[$event->status->value] ?? 'bg-black/[0.04] text-black/60' }}">
                            {{ $statusLabels[$event->status->value] ?? $event->status->value }}
                        </span>
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-black/60">Vendedor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $event->seller?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Fecha</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $event->collected_on?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Método</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $paymentLabels[$event->payment_method->value] ?? $event->payment_method->value }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Tipo</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $kindLabels[$event->kind->value] ?? $event->kind->value }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="font-medium text-black/60">Comprobante</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $event->matchedReceipt?->number ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($isApprover && $event->status === CollectionStatus::Pending)
                    <button type="button" wire:click="openMatchPanel('{{ $event->id }}')"
                        class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">
                        Conciliar
                    </button>
                @endif

                @if ($event->status === CollectionStatus::Matched)
                    <button type="button" wire:click="issueComprobante('{{ $event->id }}')"
                        class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">
                        Emitir comprobante
                    </button>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-black/15 bg-white px-4 py-10 text-center">
                <p class="text-sm font-medium text-ink">Sin cobranzas con estos filtros.</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        Limpiar filtros
                    </button>
                @endif
            </div>
        @endforelse
    </section>

    @if ($events->hasPages())
        <section class="rounded-2xl border border-black/10 bg-white p-3 shadow-sm">
            {{ $events->links('components.pagination') }}
        </section>
    @endif
</div>
