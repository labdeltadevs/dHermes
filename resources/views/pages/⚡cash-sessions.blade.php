<?php

use App\Enums\CashSessionStatus;
use App\Enums\TenantRole;
use App\Models\CashSession;
use App\Models\TenantUser;
use App\Queries\CashSessionQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Arqueos')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sellerId = '';

    public string $currency = '';

    public string $from = '';

    public string $to = '';

    public bool $showOpenForm = false;

    public string $openCurrency = '';

    public string $openNotes = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CashSession::class);
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

    public function toggleOpenForm(): void
    {
        $this->showOpenForm = ! $this->showOpenForm;
        $this->resetValidation();
    }

    public function openSession(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $this->validate([
            'openCurrency' => ['required', 'string', 'size:3'],
            'openNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'openCurrency.required' => 'Selecciona la moneda del arqueo.',
            'openCurrency.size' => 'Selecciona una moneda válida.',
        ]);

        try {
            app(\App\Actions\Cash\OpenCashSession::class)->handle($user, [
                'currency' => $this->openCurrency,
                'notes' => $this->openNotes !== '' ? $this->openNotes : null,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field === 'currency' ? 'openCurrency' : $field, $messages[0]);
            }

            return;
        }

        $this->reset('openCurrency', 'openNotes', 'showOpenForm');
        $this->resetPage();
        session()->flash('status', 'Arqueo abierto correctamente.');
    }

    public function render()
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $tenant = $membership->tenant;
        $filters = $this->filters();
        $query = app(CashSessionQuery::class);

        $canSell = $membership->role->sells();
        $canApprove = $membership->role->canApprove();

        $sellers = $canApprove
            ? TenantUser::query()
                ->where('active', true)
                ->whereIn('role', [TenantRole::Seller->value, TenantRole::Collector->value])
                ->with('user:id,name')
                ->get()
                ->sortBy(fn (TenantUser $member): string => (string) $member->user?->name)
                ->values()
            : collect();

        $currencies = collect([$tenant?->local_currency, 'BOB', 'USD'])
            ->map(fn (?string $value): string => strtoupper((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        return $this->view()->with([
            'cashSessions' => $query->filtered($user, $filters)->paginate(25)->onEachSide(1)->withQueryString(),
            'summary' => $query->summary($user),
            'sellers' => $sellers,
            'currencies' => $currencies,
            'canSell' => $canSell,
            'canApprove' => $canApprove,
            'sellerName' => $membership->role === TenantRole::Seller
                ? ($membership->salesRep?->name ?? $user->name)
                : null,
            'statusLabels' => [
                CashSessionStatus::Open->value => 'Abierto',
                CashSessionStatus::Submitted->value => 'Enviado',
                CashSessionStatus::Approved->value => 'Aprobado',
                CashSessionStatus::Rejected->value => 'Rechazado',
            ],
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
$statusClasses = fn (CashSessionStatus $value): string => match ($value) {
    CashSessionStatus::Open => 'bg-emerald-50 text-emerald-700',
    CashSessionStatus::Submitted => 'bg-amber-50 text-amber-700',
    CashSessionStatus::Approved => 'bg-brand-50 text-brand-800',
    CashSessionStatus::Rejected => 'bg-rose-50 text-rose-700',
};
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Caja y cobranzas</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Arqueos</h1>
            <p class="mt-1.5 text-sm text-black/60">Sesiones de caja y conciliación de cobros.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($canSell)
                <button type="button" wire:click="toggleOpenForm"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80">
                    {{ $showOpenForm ? 'Cancelar' : 'Abrir arqueo' }}
                </button>
            @endif

            @if ($search !== '' || $status !== '' || $sellerId !== '' || $currency !== '' || $from !== '' || $to !== '')
                <button type="button" wire:click="clearFilters"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                    Limpiar filtros
                </button>
            @endif
        </div>
    </section>

    @if ($canSell && $showOpenForm)
        <section class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-ink">Abrir arqueo</h2>
            <p class="mt-1 text-sm text-black/60">Solo puede existir un arqueo abierto por moneda.</p>

            <form wire:submit="openSession" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="open-currency" class="mb-1.5 block text-sm font-medium text-black/70">Moneda</label>
                    <select id="open-currency" wire:model="openCurrency"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        <option value="">Seleccionar</option>
                        @foreach ($currencies as $currencyOption)
                            <option value="{{ $currencyOption }}">{{ $currencyOption }}</option>
                        @endforeach
                    </select>
                    @error('openCurrency')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="open-notes" class="mb-1.5 block text-sm font-medium text-black/70">Notas (opcional)</label>
                    <textarea id="open-notes" rows="2" wire:model="openNotes"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm"></textarea>
                    @error('openNotes')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50 sm:w-auto">
                        Abrir arqueo
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            CashSessionStatus::Open->value => ['label' => 'Abiertos', 'tone' => 'text-emerald-700'],
            CashSessionStatus::Submitted->value => ['label' => 'Enviados', 'tone' => 'text-amber-700'],
            CashSessionStatus::Approved->value => ['label' => 'Aprobados', 'tone' => 'text-brand-800'],
            CashSessionStatus::Rejected->value => ['label' => 'Rechazados', 'tone' => 'text-rose-700'],
        ] as $statusKey => $meta)
            <article wire:key="kpi-{{ $statusKey }}" class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">{{ $meta['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $meta['tone'] }}">{{ number_format($summary['counts'][$statusKey], 0, ',', '.') }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-ink">Totales por moneda</h2>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($summary['totals'] as $row)
                <article wire:key="currency-{{ $row->currency }}" class="rounded-xl bg-canvas p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-black/60">{{ $row->currency }}</p>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-black/60">Esperado</dt>
                            <dd class="font-semibold text-ink">{{ $money($row->expected) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-black/60">Contado</dt>
                            <dd class="font-semibold text-ink">{{ $row->counted === null ? '—' : $money($row->counted) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-black/60">Diferencia</dt>
                            <dd class="font-semibold {{ (float) $row->difference < 0 ? 'text-rose-700' : 'text-ink' }}">
                                {{ $row->difference === null ? '—' : $money($row->difference) }}
                            </dd>
                        </div>
                    </dl>
                </article>
            @empty
                <p class="text-sm text-black/60">Aún no hay montos registrados.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="cash-search" class="mb-1.5 block text-sm font-medium text-black/70">Buscar</label>
                <input id="cash-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Vendedor"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="cash-status" class="mb-1.5 block text-sm font-medium text-black/70">Estado</label>
                <select id="cash-status" wire:model.live="status"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todos</option>
                    @foreach ($statusLabels as $statusValue => $statusLabel)
                        <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>

            @if ($canApprove && $sellers->isNotEmpty())
                <div>
                    <label for="cash-seller" class="mb-1.5 block text-sm font-medium text-black/70">Vendedor</label>
                    <select id="cash-seller" wire:model.live="sellerId"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        <option value="">Todos</option>
                        @foreach ($sellers as $sellerMember)
                            <option value="{{ $sellerMember->user_id }}">{{ $sellerMember->user?->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="cash-currency" class="mb-1.5 block text-sm font-medium text-black/70">Moneda</label>
                <select id="cash-currency" wire:model.live="currency"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todas</option>
                    @foreach ($currencies as $currencyOption)
                        <option value="{{ $currencyOption }}">{{ $currencyOption }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cash-from" class="mb-1.5 block text-sm font-medium text-black/70">Desde</label>
                <input id="cash-from" type="date" wire:model.live="from"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="cash-to" class="mb-1.5 block text-sm font-medium text-black/70">Hasta</label>
                <input id="cash-to" type="date" wire:model.live="to"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Vendedor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Apertura</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Moneda</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Esperado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Contado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Diferencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($cashSessions as $cashSession)
                        <tr wire:key="cash-session-{{ $cashSession->id }}" class="transition hover:bg-brand-50/40">
                            <td class="px-4 py-4 text-sm font-medium text-ink">{{ $cashSession->seller?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $cashSession->opened_at?->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $cashSession->currency }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-ink">{{ $money($cashSession->expected_amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-black/70">{{ $cashSession->counted_amount === null ? '—' : $money($cashSession->counted_amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold {{ (float) $cashSession->difference < 0 ? 'text-rose-700' : 'text-ink' }}">
                                {{ $cashSession->difference === null ? '—' : $money($cashSession->difference) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses($cashSession->status) }}">
                                    {{ $statusLabels[$cashSession->status->value] }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <a href="{{ route('cash-sessions.show', $cashSession) }}" wire:navigate
                                    class="text-sm font-semibold text-brand-700 hover:text-brand-800">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin arqueos con estos filtros.</p>
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

    <section class="space-y-3 md:hidden" aria-label="Arqueos">
        @forelse ($cashSessions as $cashSession)
            <article wire:key="cash-session-card-{{ $cashSession->id }}" class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $cashSession->seller?->name ?? '—' }}</p>
                        <p class="mt-0.5 text-xs text-black/60">{{ $cashSession->opened_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses($cashSession->status) }}">
                        {{ $statusLabels[$cashSession->status->value] }}
                    </span>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-black/60">Moneda</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $cashSession->currency }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Esperado</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $money($cashSession->expected_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Contado</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $cashSession->counted_amount === null ? '—' : $money($cashSession->counted_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Diferencia</dt>
                        <dd class="mt-0.5 font-semibold {{ (float) $cashSession->difference < 0 ? 'text-rose-700' : 'text-ink' }}">
                            {{ $cashSession->difference === null ? '—' : $money($cashSession->difference) }}
                        </dd>
                    </div>
                </dl>

                <a href="{{ route('cash-sessions.show', $cashSession) }}" wire:navigate
                    class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                    Ver arqueo
                </a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-black/15 bg-white px-4 py-10 text-center">
                <p class="text-sm font-medium text-ink">Sin arqueos con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>

    @if ($cashSessions->hasPages())
        <section class="rounded-2xl border border-black/10 bg-white p-3 shadow-sm">
            {{ $cashSessions->links('components.pagination') }}
        </section>
    @endif
</div>
