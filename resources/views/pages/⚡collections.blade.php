<?php

use App\Actions\Collection\AcceptCollection;
use App\Actions\Collection\CancelCollection;
use App\Actions\Collection\RejectCollection;
use App\Enums\CollectionStatus;
use App\Enums\Permission;
use App\Enums\TenantRole;
use App\Models\CollectionEvent;
use App\Models\TenantUser;
use App\Models\User;
use App\Queries\CollectionQuery;
use App\Support\Verification\IssueVerificationCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Cobranzas')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sellerId = '';

    public string $currency = '';

    public string $from = '';

    public string $to = '';

    public string $pendingActionEventId = '';

    public string $erpDocnum = '';

    public bool $fundsReceived = false;

    public string $rejectionReason = '';

    public string $cancelEventId = '';

    public string $cancelReason = '';

    public bool $showCreateModal = false;

    public string $createInvoiceId = '';

    public string $feedback = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', CollectionEvent::class);
    }

    public function openCreateModal(): void
    {
        $membership = auth()->user()->currentMembership();

        abort_if($membership === null, 403);
        abort_unless($membership->role->sells(), 403);

        $this->createInvoiceId = '';
        $this->feedback = '';
        $this->showCreateModal = true;
        $this->resetValidation();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->createInvoiceId = '';
        $this->resetValidation();
    }

    public function onCollectionCreated(): void
    {
        $this->showCreateModal = false;
        $this->createInvoiceId = '';
        $this->feedback = 'Cobro registrado correctamente.';
        $this->resetPage();
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

    public function openActionPanel(string $eventId): void
    {
        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->find($eventId);

        abort_if($event === null, 404);

        if ($event->status !== CollectionStatus::Pending) {
            $this->addError('status', 'Solo se pueden revisar cobranzas pendientes.');

            return;
        }

        Gate::authorize('accept', $event);

        $this->pendingActionEventId = $event->id;
        $this->erpDocnum = '';
        $this->fundsReceived = false;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function closeActionPanel(): void
    {
        $this->reset(['pendingActionEventId', 'erpDocnum', 'fundsReceived', 'rejectionReason']);
        $this->resetValidation();
    }

    public function acceptEvent(): void
    {
        $this->validate(['pendingActionEventId' => ['required', 'string']]);

        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->findOrFail($this->pendingActionEventId);

        $this->validate(
            [
                'erpDocnum' => [
                    'required',
                    'string',
                    'max:32',
                    'regex:/^\d+$/',
                    Rule::unique('collection_events', 'erp_docnum')
                        ->where(fn ($query) => $query->where('tenant_id', $event->tenant_id))
                        ->ignore($event->id),
                ],
                'fundsReceived' => ['accepted'],
            ],
            [
                'erpDocnum.required' => 'El DocNum del ERP es obligatorio.',
                'erpDocnum.regex' => 'El DocNum del ERP debe ser numérico.',
                'erpDocnum.unique' => 'El DocNum del ERP ya fue registrado en esta empresa.',
                'fundsReceived.accepted' => 'Debes confirmar la recepción del efectivo o comprobante.',
            ],
        );

        app(AcceptCollection::class)->handle($user, $event, $this->erpDocnum, $this->fundsReceived);

        $this->feedback = 'Cobranza aceptada.';
        $this->closeActionPanel();
        $this->resetPage();
    }

    public function rejectEvent(): void
    {
        $this->validate(
            [
                'pendingActionEventId' => ['required', 'string'],
                'rejectionReason' => ['required', 'string', 'max:1000'],
            ],
            [
                'rejectionReason.required' => 'El motivo del rechazo es obligatorio.',
            ],
        );

        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->findOrFail($this->pendingActionEventId);

        app(RejectCollection::class)->handle($user, $event, $this->rejectionReason);

        $this->feedback = 'Cobranza rechazada.';
        $this->closeActionPanel();
        $this->resetPage();
    }

    public function openCancelPanel(string $eventId): void
    {
        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->find($eventId);

        abort_if($event === null, 404);

        Gate::authorize('cancel', $event);

        $this->cancelEventId = $event->id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function closeCancelPanel(): void
    {
        $this->reset(['cancelEventId', 'cancelReason']);
        $this->resetValidation();
    }

    public function cancelEvent(): void
    {
        $this->validate(
            [
                'cancelEventId' => ['required', 'string'],
                'cancelReason' => ['required', 'string', 'max:1000'],
            ],
            [
                'cancelReason.required' => 'El motivo de la anulación es obligatorio.',
            ],
        );

        $user = auth()->user();
        $event = app(CollectionQuery::class)->for($user)->findOrFail($this->cancelEventId);

        app(CancelCollection::class)->handle($user, $event, $this->cancelReason);

        $this->feedback = 'Cobranza anulada.';
        $this->closeCancelPanel();
        $this->resetPage();
    }

    public function issueComprobante(string $eventId): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $event = app(CollectionQuery::class)->for($user)->findOrFail($eventId);

        abort_unless($event->status === CollectionStatus::Accepted, 403);

        $code = app(IssueVerificationCode::class)->handle($user, $event);

        $this->redirect(route('verification.print', $code->code));
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $query = app(CollectionQuery::class);
        $filters = $this->filters();
        $isApprover = $membership->role->canApprove();
        $canCreate = $membership->role->sells();
        $canCreateFlash = $user->can(Permission::CollectionsCreateFlash->value);
        $actionEvent = $this->pendingActionEventId !== ''
            ? $query->for($user)->find($this->pendingActionEventId)
            : null;
        $cancelEvent = $this->cancelEventId !== ''
            ? $query->for($user)->find($this->cancelEventId)
            : null;

        return $this->view()->with([
            'events' => $query->filtered($user, $filters)->paginate(25)->onEachSide(1)->withQueryString(),
            'summary' => $query->summaryByCurrency($user, $filters),
            'isApprover' => $isApprover,
            'canCreate' => $canCreate,
            'canCreateFlash' => $canCreateFlash,
            'actionEvent' => $actionEvent,
            'cancelEvent' => $cancelEvent,
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
            'currencies' => $query->for($user)->reorder()->select('currency')->distinct()->orderBy('currency')->pluck('currency'),
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
    $money = fn(string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
    $statusLabels = [
        'pending' => 'En revisión',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
        'cancelled' => 'Anulado',
    ];
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-800',
        'accepted' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'cancelled' => 'bg-neutral-200 text-neutral-700',
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
    $hasFilters =
        $search !== '' || $status !== '' || $sellerId !== '' || $currency !== '' || $from !== '' || $to !== '';
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Cartera y cobranzas</p>
            <h1 class="page-title">Cobranzas</h1>
            <p class="page-subtitle">Revisión y aceptación de cobros informados por los vendedores.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="btn btn-outline">
                    Limpiar filtros
                </button>
            @endif

            @if ($canCreateFlash)
                <a href="{{ route('collections.flash') }}" wire:navigate class="btn btn-outline">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                    Cobros flash
                </a>
            @endif

            @if ($canCreate)
                <button type="button" wire:click="openCreateModal" class="btn btn-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo cobro
                </button>
            @endif
        </div>
    </section>

    @if ($feedback !== '')
        <div class="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status">
            <span>{{ $feedback }}</span>

            <button type="button" wire:click="$set('feedback', '')"
                class="font-medium text-emerald-700 transition hover:text-emerald-900">
                Cerrar
            </button>
        </div>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article class="stat">
            <p class="stat-label">En revisión</p>
            <p class="stat-value text-amber-700">{{ number_format($summary->sum('pending_count'), 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Aceptados</p>
            <p class="stat-value text-emerald-700">{{ number_format($summary->sum('accepted_count'), 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Rechazados</p>
            <p class="stat-value text-rose-700">{{ number_format($summary->sum('rejected_count'), 0, ',', '.') }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Anulados</p>
            <p class="stat-value text-neutral-600">{{ number_format($summary->sum('cancelled_count'), 0, ',', '.') }}
            </p>
        </article>

        <article class="stat">
            <p class="stat-label">Total aceptado</p>
            <div class="mt-2 space-y-1">
                @forelse ($summary as $row)
                    <p wire:key="summary-{{ $row->currency }}" class="text-lg font-semibold text-ink">
                        {{ $row->currency }} {{ $money($row->accepted_total) }}</p>
                @empty
                    <p class="text-lg font-semibold text-muted">—</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="card p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="collections-search" class="label">Buscar</label>
                <input id="collections-search" type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Factura, cliente, DocNum" class="input">
            </div>

            <div>
                <label for="collections-status" class="label">Estado</label>
                <select id="collections-status" wire:model.live="status" class="select">
                    <option value="">Todos</option>
                    <option value="pending">En revisión</option>
                    <option value="accepted">Aceptado</option>
                    <option value="rejected">Rechazado</option>
                    <option value="cancelled">Anulado</option>
                </select>
            </div>

            @if ($sellers->isNotEmpty())
                <div>
                    <label for="collections-seller" class="label">Vendedor</label>
                    <select id="collections-seller" wire:model.live="sellerId" class="select">
                        <option value="">Todos</option>
                        @foreach ($sellers as $seller)
                            <option wire:key="seller-option-{{ $seller->id }}" value="{{ $seller->id }}">
                                {{ $seller->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="collections-currency" class="label">Moneda</label>
                <select id="collections-currency" wire:model.live="currency" class="select">
                    <option value="">Todas</option>
                    @foreach ($currencies as $currencyOption)
                        <option wire:key="currency-option-{{ $currencyOption }}" value="{{ $currencyOption }}">
                            {{ $currencyOption }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="collections-from" class="label">Desde</label>
                <input id="collections-from" type="date" wire:model.live="from" class="input">
            </div>

            <div>
                <label for="collections-to" class="label">Hasta</label>
                <input id="collections-to" type="date" wire:model.live="to" class="input">
            </div>
        </div>
    </section>

    @if ($errors->has('status'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first('status') }}
        </div>
    @endif

    @if ($cancelEvent instanceof CollectionEvent)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-panel-title"
            wire:click.self="closeCancelPanel"
            wire:keydown.escape.window="closeCancelPanel"
        >
            <section id="cancel-panel"
                class="flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-t-3xl border border-rose-200 bg-surface shadow-pop sm:rounded-3xl">
                <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="cancel-panel-title" class="text-lg font-semibold text-ink">Anular cobranza</h2>
                        <p class="mt-1 text-sm text-muted">
                            La anulación deja el cobro fuera del flujo y queda registrada en la auditoría.
                        </p>
                    </div>

                    <button type="button" wire:click="closeCancelPanel"
                        class="text-sm font-medium text-muted transition hover:text-ink">
                        Cerrar
                    </button>
                </header>

                <div class="overflow-y-auto p-5 sm:p-6">
                    <dl class="grid gap-3 rounded-xl border border-line bg-surface p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-muted">Factura</dt>
                            <dd class="mt-0.5 font-semibold text-ink">{{ $cancelEvent->invoice?->number ?? $cancelEvent->flash_invoice_number ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted">Cliente</dt>
                            <dd class="mt-0.5 font-semibold text-ink">
                                {{ $cancelEvent->invoice?->customer?->name ?? $cancelEvent->flash_customer_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted">Monto</dt>
                            <dd class="mt-0.5 font-semibold text-ink">{{ $cancelEvent->currency }}
                                {{ $money($cancelEvent->amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-muted">Fecha de cobro</dt>
                            <dd class="mt-0.5 font-semibold text-ink">
                                {{ $cancelEvent->collected_on?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4">
                        <label for="cancel-reason" class="label">Motivo de la anulación</label>
                        <textarea id="cancel-reason" wire:model="cancelReason" rows="3"
                            placeholder="Explica por qué se anula el cobro" class="input"></textarea>

                        @error('cancelReason')
                            <p class="field-error">{{ $message }}</p>
                        @enderror

                        @unless ($isApprover)
                            <p class="mt-2 text-xs text-muted">Solo puedes anular cobros registrados el día de hoy.</p>
                        @endunless
                    </div>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeCancelPanel" class="btn btn-outline">
                            Cancelar
                        </button>

                        <button type="button" wire:click="cancelEvent" wire:loading.attr="disabled"
                            class="btn btn-danger-soft">
                            Anular cobranza
                        </button>
                    </div>
                </div>
            </section>
        </div>
    @endif

    @if ($actionEvent instanceof CollectionEvent)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="action-panel-title"
            wire:click.self="closeActionPanel"
            wire:keydown.escape.window="closeActionPanel"
        >
            <section id="action-panel"
                class="flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl border border-sky-200 bg-surface shadow-pop sm:rounded-3xl">
                <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                <div>
                        <h2 id="action-panel-title" class="text-lg font-semibold text-ink">Revisar cobranza</h2>
                        <p class="mt-1 text-sm text-muted">
                            Verifica los datos y confirma la entrega física. Registra el cobro en el ERP y luego ingresa el
                            DocNum para aceptarlo.
                        </p>
                    </div>

                    <button type="button" wire:click="closeActionPanel"
                        class="text-sm font-medium text-muted transition hover:text-ink">
                        Cerrar
                    </button>
                </header>

                <div class="overflow-y-auto p-5 sm:p-6">
            <dl class="grid gap-3 rounded-xl border border-line bg-surface p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium text-muted">Factura</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $actionEvent->invoice?->number ?? $actionEvent->flash_invoice_number ?? '—' }}</dd>
                    <dd class="font-mono text-[11px] text-black/40">{{ $actionEvent->invoice?->docentry ?? ($actionEvent->isFlash() ? 'Cobro flash' : '—') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Cliente</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $actionEvent->invoice?->customer?->name ?? $actionEvent->flash_customer_name ?? '—' }}</dd>
                    <dd class="font-mono text-[11px] text-black/40">
                        {{ $actionEvent->invoice?->customer?->cardcode ?? $actionEvent->flash_cardcode ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Vendedor</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $actionEvent->seller?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Monto</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $actionEvent->currency }}
                        {{ $money($actionEvent->amount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Fecha de cobro</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $actionEvent->collected_on?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Método</dt>
                    <dd class="mt-0.5 font-semibold text-ink">
                        {{ $paymentLabels[$actionEvent->payment_method->value] ?? $actionEvent->payment_method->value }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Tipo</dt>
                    <dd class="mt-0.5 font-semibold text-ink">
                        {{ $kindLabels[$actionEvent->kind->value] ?? $actionEvent->kind->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Voucher</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $actionEvent->voucher_code ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <dt class="text-xs font-medium text-muted">Comprobante adjunto</dt>
                    <dd class="mt-0.5">
                        @if ($actionEvent->proof_path)
                            <a href="{{ route('collections.proof', $actionEvent->id) }}" target="_blank"
                                rel="noopener" class="font-semibold text-brand-700 hover:text-brand-800">
                                Ver comprobante
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                </div>
                @if ($actionEvent->notes)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-medium text-muted">Notas</dt>
                        <dd class="mt-0.5 text-ink">{{ $actionEvent->notes }}</dd>
                    </div>
                @endif
            </dl>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <h3 class="text-sm font-semibold text-ink">Aceptar cobro</h3>
                    <p class="mt-1 text-xs text-muted">
                        Ingresa el DocNum que se registró en el ERP para este cobro.
                    </p>

                    <div class="mt-3">
                        <label for="erp-docnum" class="label">DocNum ERP</label>
                        <input id="erp-docnum" type="text" inputmode="numeric" wire:model="erpDocnum"
                            placeholder="Ej. 100341331" class="input" autocomplete="off">

                        @error('erpDocnum')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label for="funds-received" class="mt-4 flex items-start gap-2 text-sm text-ink">
                        <input id="funds-received" type="checkbox" wire:model="fundsReceived"
                            class="mt-0.5 size-4 rounded border-line text-emerald-600 focus:ring-emerald-500">
                        <span>Confirmo que recibí el efectivo o el comprobante de depósito del vendedor.</span>
                    </label>

                    @error('fundsReceived')
                        <p class="field-error">{{ $message }}</p>
                    @enderror

                    <button type="button" wire:click="acceptEvent" wire:loading.attr="disabled"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 btn btn-dark">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Aceptar cobro
                    </button>
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
                    <h3 class="text-sm font-semibold text-ink">Rechazar cobro</h3>
                    <p class="mt-1 text-xs text-muted">
                        Indica por qué la información del cobro no es válida.
                    </p>

                    <div class="mt-3">
                        <label for="rejection-reason" class="label">Motivo del rechazo</label>
                        <textarea id="rejection-reason" wire:model="rejectionReason" rows="3"
                            placeholder="Explica por qué se rechaza el cobro" class="input"></textarea>

                        @error('rejectionReason')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="button" wire:click="rejectEvent" wire:loading.attr="disabled"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 btn btn-danger-soft">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Rechazar cobro
                    </button>
                </div>
            </div>
                </div>
            </section>
        </div>
    @endif

    <section
        class="hidden overflow-hidden rounded-2xl border border-black/[0.07] bg-white shadow-[0_1px_2px_rgb(0_0_0/0.05)] md:block">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1240px] border-collapse text-[13.5px] leading-5">
                <thead class="sticky top-0 z-10">
                    <tr class="border-b border-black/[0.07] bg-[#FAFAF8] text-left">
                        <th
                            class="whitespace-nowrap px-4 py-3 pl-5 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Factura</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Cliente</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Vendedor</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Fecha</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Método</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Voucher</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Comprobante</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Monto</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Estado</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Doc. ERP</th>
                        <th
                            class="whitespace-nowrap px-4 py-3 pr-5 text-right text-[11px] font-semibold uppercase tracking-[0.06em] text-black/45">
                            Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/[0.06]">
                    @forelse ($events as $event)
                        @php
                            $canCancel = auth()->user()->can('cancel', $event);
                            $showReview = $isApprover && $event->status === CollectionStatus::Pending;
                            $showReceipt = $event->status === CollectionStatus::Accepted;
                            $hasActions = $showReview || $canCancel || $showReceipt;
                            $invoiceNumber = $event->invoice?->number ?? $event->flash_invoice_number;
                            $invoiceReference = $event->invoice?->docentry ?? ($event->isFlash() ? 'Cobro flash' : null);
                            $customerName = $event->invoice?->customer?->name ?? $event->flash_customer_name;
                            $customerCardcode = $event->invoice?->customer?->cardcode ?? $event->flash_cardcode;
                            $initial = mb_strtoupper(mb_substr($customerName ?? '—', 0, 1));
                        @endphp
                        <tr wire:key="collection-{{ $event->id }}"
                            class="group transition-colors hover:bg-brand-50/50">
                            {{-- Factura --}}
                            <td class="whitespace-nowrap px-4 py-3.5 pl-5 align-middle">
                                <p class="font-semibold tabular-nums text-ink">{{ $invoiceNumber ?? '—' }}
                                </p>
                                <p class="mt-0.5 font-mono text-[11px] tabular-nums text-black/40">
                                    {{ $invoiceReference ?? '—' }}</p>
                            </td>

                            {{-- Cliente --}}
                            <td class="px-4 py-3.5 align-middle">
                                <div class="flex max-w-[220px] items-center gap-2.5">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-[11px] font-bold text-white">{{ $initial }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-ink"
                                            title="{{ $customerName }}">
                                            {{ $customerName ?? '—' }}</p>
                                        <p class="mt-0.5 truncate font-mono text-[11px] text-black/40">
                                            {{ $customerCardcode ?? '' }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Vendedor / Fecha / Metodo --}}
                            <td class="max-w-[140px] truncate px-4 py-3.5 align-middle text-black/60">
                                {{ $event->seller?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 align-middle tabular-nums text-black/60">
                                {{ $event->collected_on?->format('d/m/Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 align-middle text-black/60">
                                {{ $paymentLabels[$event->payment_method->value] ?? $event->payment_method->value }}
                            </td>

                            {{-- Voucher --}}
                            <td class="whitespace-nowrap px-4 py-3.5 align-middle">
                                @if ($event->voucher_code)
                                    <code
                                        class="rounded-md bg-black/[0.05] px-1.5 py-0.5 font-mono text-xs font-medium text-ink">{{ $event->voucher_code }}</code>
                                @else
                                    <span class="text-black/30">—</span>
                                @endif
                            </td>

                            {{-- Comprobante --}}
                            <td class="whitespace-nowrap px-4 py-3.5 align-middle">
                                @if ($event->proof_path)
                                    <a href="{{ route('collections.proof', $event->id) }}" target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 text-[13px] font-medium text-brand-700 underline decoration-brand-200 underline-offset-2 hover:text-brand-800 hover:decoration-brand-500">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                        </svg>
                                        Ver
                                    </a>
                                @else
                                    <span class="text-black/30">—</span>
                                @endif
                            </td>

                            {{-- Monto --}}
                            <td class="whitespace-nowrap px-4 py-3.5 align-middle text-right">
                                <p class="font-semibold tabular-nums tracking-tight text-ink">{{ $event->currency }}
                                    {{ $money($event->amount) }}</p>
                                <p class="mt-0.5 text-[11px] text-black/40">
                                    {{ $kindLabels[$event->kind->value] ?? $event->kind->value }}</p>
                            </td>

                            {{-- Estado --}}
                            <td class="whitespace-nowrap px-4 py-3.5 align-middle">
                                <span
                                    class="badge {{ $statusClasses[$event->status->value] ?? 'bg-black/[0.04] text-muted' }} inline-flex items-center gap-1.5"
                                    @if ($event->status === CollectionStatus::Rejected && $event->rejection_reason) title="{{ $event->rejection_reason }}" @endif>
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                    {{ $statusLabels[$event->status->value] ?? $event->status->value }}
                                </span>
                            </td>

                            {{-- Doc. ERP --}}
                            <td
                                class="whitespace-nowrap px-4 py-3.5 align-middle font-mono text-xs tabular-nums text-black/60">
                                {{ $event->erp_docnum ?? '—' }}</td>

                            {{-- Acciones --}}
                            <td class="whitespace-nowrap px-4 py-3.5 pr-5 align-middle">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($showReview)
                                        <button type="button" wire:click="openActionPanel('{{ $event->id }}')"
                                            title="Revisar"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-ink px-3 text-xs font-semibold text-white transition hover:bg-black/80">
                                            <svg class="size-3.5" fill="none" stroke="currentColor"
                                                stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            Revisar
                                        </button>
                                    @endif

                                    @if ($showReceipt)
                                        <button type="button" wire:click="issueComprobante('{{ $event->id }}')"
                                            title="Emitir comprobante"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-sky-700 transition hover:bg-sky-50">
                                            <svg class="size-4" fill="none" stroke="currentColor"
                                                stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M16.5 1.5H9a2.25 2.25 0 0 0-2.25 2.25V15m0 3v2.25A2.25 2.25 0 0 0 9 22.5h6a2.25 2.25 0 0 0 2.25-2.25V15m0-3H9" />
                                            </svg>
                                        </button>
                                    @endif

                                    @if ($canCancel)
                                        <button type="button" wire:click="openCancelPanel('{{ $event->id }}')"
                                            title="Anular"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-black/40 transition hover:bg-rose-50 hover:text-rose-700">
                                            <svg class="size-4" fill="none" stroke="currentColor"
                                                stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    @endif

                                    @if (!$hasActions)
                                        <span class="text-black/30">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-14 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <span
                                        class="flex size-11 items-center justify-center rounded-full bg-black/[0.04]">
                                        <svg class="size-5 text-black/40" fill="none" stroke="currentColor"
                                            stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                        </svg>
                                    </span>
                                    <p class="mt-3 text-sm font-semibold text-ink">Sin cobranzas con estos filtros</p>
                                    <p class="mt-1 text-[13px] text-black/50">Prueba ajustando fechas, vendedor o
                                        estado.</p>
                                    @if ($hasFilters)
                                        <button type="button" wire:click="clearFilters"
                                            class="mt-3 inline-flex h-9 items-center rounded-lg border border-black/10 bg-white px-3.5 text-[13px] font-semibold text-ink transition hover:bg-black/[0.03]">
                                            Limpiar filtros
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Cobranzas registradas">
        @forelse ($events as $event)
            <article wire:key="collection-card-{{ $event->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $event->invoice?->customer?->name ?? $event->flash_customer_name ?? '—' }}</p>
                        <p class="mt-0.5 text-xs text-muted">{{ $event->invoice?->number ?? $event->flash_invoice_number ?? '—' }} ·
                            {{ $event->invoice?->customer?->cardcode ?? $event->flash_cardcode ?? '' }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-ink">{{ $event->currency }} {{ $money($event->amount) }}
                        </p>
                        <span
                            class="badge mt-1 {{ $statusClasses[$event->status->value] ?? 'bg-black/[0.04] text-muted' }}">
                            {{ $statusLabels[$event->status->value] ?? $event->status->value }}
                        </span>
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-muted">Vendedor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $event->seller?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Fecha</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $event->collected_on?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Método</dt>
                        <dd class="mt-0.5 font-semibold text-ink">
                            {{ $paymentLabels[$event->payment_method->value] ?? $event->payment_method->value }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Voucher</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $event->voucher_code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Tipo</dt>
                        <dd class="mt-0.5 font-semibold text-ink">
                            {{ $kindLabels[$event->kind->value] ?? $event->kind->value }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted">Doc. ERP</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $event->erp_docnum ?? '—' }}</dd>
                    </div>
                    @if ($event->status === CollectionStatus::Rejected)
                        <div class="col-span-2">
                            <dt class="font-medium text-muted">Motivo del rechazo</dt>
                            <dd class="mt-0.5 font-semibold text-ink">
                                {{ $event->rejection_reason ?? 'Sin motivo registrado' }}</dd>
                        </div>
                    @endif
                    @if ($event->proof_path)
                        <div class="col-span-2">
                            <dt class="font-medium text-muted">Comprobante adjunto</dt>
                            <dd class="mt-0.5">
                                <a href="{{ route('collections.proof', $event->id) }}" target="_blank"
                                    rel="noopener" class="font-semibold text-brand-700 hover:text-brand-800">
                                    Ver comprobante
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($isApprover && $event->status === CollectionStatus::Pending)
                        <button type="button" wire:click="openActionPanel('{{ $event->id }}')"
                            class="inline-flex items-center justify-center gap-1.5 flex-1 min-w-[120px] btn btn-soft">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Revisar
                        </button>
                    @endif

                    @if (auth()->user()->can('cancel', $event))
                        <button type="button" wire:click="openCancelPanel('{{ $event->id }}')"
                            class="inline-flex items-center justify-center gap-1.5 flex-1 min-w-[120px] btn btn-danger-soft">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Anular
                        </button>
                    @endif

                    @if ($event->status === CollectionStatus::Accepted)
                        <button type="button" wire:click="issueComprobante('{{ $event->id }}')"
                            class="inline-flex items-center justify-center gap-1.5 flex-1 min-w-[120px] btn btn-soft">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Emitir comprobante
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">
                <p class="text-sm font-medium text-ink">Sin cobranzas con estos filtros.</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters"
                        class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        Limpiar filtros
                    </button>
                @endif
            </div>
        @endforelse
    </section>

    @if ($events->hasPages())
        <section class="card p-3">
            {{ $events->links('components.pagination') }}
        </section>
    @endif

    @if ($showCreateModal)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="create-collection-title"
            wire:click.self="closeCreateModal"
            wire:keydown.escape.window="closeCreateModal"
        >
            <div
                class="flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl bg-surface shadow-pop sm:rounded-3xl"
            >
                <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="create-collection-title" class="text-lg font-semibold text-ink">
                            Registrar cobro
                        </h2>
                        <p class="mt-0.5 text-sm text-muted">
                            Informa un cobro para una factura abierta de tu cartera.
                        </p>
                    </div>

                    <button type="button" wire:click="closeCreateModal"
                        class="text-sm font-medium text-muted transition hover:text-ink">
                        Cerrar
                    </button>
                </header>

                <div class="overflow-y-auto p-5 sm:p-6">
                    <livewire:pages::collection-create
                        :invoice="$createInvoiceId"
                        :embedded="true"
                        @collection-created="onCollectionCreated"
                    />
                </div>
            </div>
        </div>
    @endif
</div>
