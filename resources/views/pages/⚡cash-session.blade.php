<?php

use App\Actions\Cash\AddCashSessionItem;
use App\Actions\Cash\ApproveCashSession;
use App\Actions\Cash\ReopenCashSession;
use App\Actions\Cash\SubmitCashSession;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\User;
use App\Queries\CashSessionQuery;
use App\Support\Verification\IssueVerificationCode;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Arqueo')] class extends Component
{
    public CashSession $cashSession;

    public string $itemPaymentMethod = 'cash';

    public string $itemAmount = '';

    public string $itemReference = '';

    public string $itemCollectionEventId = '';

    public string $countedAmount = '';

    public string $notes = '';

    public string $rejectReason = '';

    public function mount(CashSession $cashSession): void
    {
        Gate::authorize('view', $cashSession);

        $this->cashSession = $cashSession;
        $this->countedAmount = $cashSession->counted_amount !== null ? (string) $cashSession->counted_amount : '';
        $this->notes = $cashSession->notes ?? '';
    }

    public function addItem(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        app(AddCashSessionItem::class)->handle($user, $this->cashSession, [
            'collection_event_id' => $this->itemCollectionEventId !== '' ? $this->itemCollectionEventId : null,
            'payment_method' => $this->itemPaymentMethod,
            'amount' => $this->itemAmount,
            'reference' => $this->itemReference !== '' ? $this->itemReference : null,
        ]);

        $this->reset('itemAmount', 'itemReference', 'itemCollectionEventId');
        $this->cashSession = $this->cashSession->refresh();
        session()->flash('status', 'Cobro agregado al arqueo.');
    }

    public function submit(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $session = app(SubmitCashSession::class)->handle($user, $this->cashSession, [
            'counted_amount' => $this->countedAmount,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->cashSession = $session;
        $this->countedAmount = $session->counted_amount !== null ? (string) $session->counted_amount : '';
        $this->notes = $session->notes ?? '';
        session()->flash('status', 'Arqueo enviado para aprobación.');
    }

    public function reopen(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $session = app(ReopenCashSession::class)->handle($user, $this->cashSession);

        $this->cashSession = $session;
        $this->countedAmount = '';
        session()->flash('status', 'Arqueo reabierto. Puedes agregar cobros nuevamente.');
    }

    public function approve(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $session = app(ApproveCashSession::class)->handle($user, $this->cashSession);

        $this->cashSession = $session;
        session()->flash('status', 'Arqueo aprobado.');
    }

    public function reject(): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $this->validate([
            'rejectReason' => ['required', 'string', 'max:1000'],
        ], [
            'rejectReason.required' => 'Indica el motivo del rechazo.',
        ]);

        $session = app(ApproveCashSession::class)->reject($user, $this->cashSession, $this->rejectReason);

        $this->cashSession = $session;
        $this->rejectReason = '';
        $this->notes = $session->notes ?? '';
        session()->flash('status', 'Arqueo rechazado.');
    }

    public function issueComprobante(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->cashSession->status === CashSessionStatus::Approved, 403);

        $code = app(IssueVerificationCode::class)->handle($user, $this->cashSession);

        $this->redirect(route('verification.print', $code->code));
    }

    public function render()
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $isOwner = $this->cashSession->seller_user_id === $user->id;
        $canManage = $isOwner && $membership->role->sells();
        $canApprove = $membership->role->canApprove();
        $isOpen = $this->cashSession->status === CashSessionStatus::Open;
        $isSubmitted = $this->cashSession->status === CashSessionStatus::Submitted;
        $isRejected = $this->cashSession->status === CashSessionStatus::Rejected;
        $isApproved = $this->cashSession->status === CashSessionStatus::Approved;
        $canPrint = $isApproved && ($isOwner || $canApprove);

        $this->cashSession->load(['seller:id,name', 'approver:id,name']);

        $items = $this->cashSession->items()
            ->with('collectionEvent')
            ->orderBy('created_at')
            ->get();

        $availableCollections = $canManage && $isOpen
            ? app(CashSessionQuery::class)->availableCollections($user, $this->cashSession)
            : collect();

        return $this->view()->with([
            'items' => $items,
            'availableCollections' => $availableCollections,
            'canManage' => $canManage,
            'canApprove' => $canApprove,
            'isOpen' => $isOpen,
            'isSubmitted' => $isSubmitted,
            'isRejected' => $isRejected,
            'canPrint' => $canPrint,
            'paymentMethods' => [
                PaymentMethod::Cash->value => 'Efectivo',
                PaymentMethod::Check->value => 'Cheque',
                PaymentMethod::Transfer->value => 'Transferencia',
                PaymentMethod::Other->value => 'Otro',
            ],
            'statusLabels' => [
                CashSessionStatus::Open->value => 'Abierto',
                CashSessionStatus::Submitted->value => 'Enviado',
                CashSessionStatus::Approved->value => 'Aprobado',
                CashSessionStatus::Rejected->value => 'Rechazado',
            ],
        ]);
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
            <a href="{{ route('cash-sessions.index') }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:text-brand-800">Volver a arqueos</a>
            <h1 class="page-title">Arqueo de {{ $cashSession->seller?->name ?? 'vendedor' }}</h1>
            <p class="page-subtitle">Apertura {{ $cashSession->opened_at?->format('d/m/Y H:i') }} · Moneda {{ $cashSession->currency }}</p>
        </div>

        <div class="flex flex-col items-start gap-2 sm:items-end">
            <span class="badge {{ $statusClasses($cashSession->status) }}">
                {{ $statusLabels[$cashSession->status->value] }}
            </span>

            @if ($canPrint)
                <button type="button" wire:click="issueComprobante" wire:loading.attr="disabled"
                    class="btn btn-soft btn-sm">
                    Emitir comprobante
                </button>
            @endif
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat">
            <p class="stat-label">Esperado</p>
            <p class="stat-value">{{ $money($cashSession->expected_amount) }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Contado</p>
            <p class="stat-value">{{ $cashSession->counted_amount === null ? '—' : $money($cashSession->counted_amount) }}</p>
        </article>

        <article class="stat">
            <p class="stat-label">Diferencia</p>
            <p class="stat-value {{ (float) $cashSession->difference < 0 ? 'text-rose-700' : 'text-ink' }}">
                {{ $cashSession->difference === null ? '—' : $money($cashSession->difference) }}
            </p>
        </article>

        <article class="stat">
            <p class="stat-label">Cierre</p>
            <p class="mt-2 text-sm font-semibold text-ink">{{ $cashSession->closed_at?->format('d/m/Y H:i') ?? 'Sin cerrar' }}</p>
            <p class="mt-1 text-xs text-muted">Aprobador: {{ $cashSession->approver?->name ?? '—' }}</p>
        </article>
    </section>

    @if ($cashSession->notes)
        <section class="card p-5">
            <h2 class="text-base font-semibold text-ink">Notas</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-muted">{{ $cashSession->notes }}</p>
        </section>
    @endif

    <section class="overflow-hidden card">
        <div class="flex flex-col gap-2 border-b border-line p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-ink">Detalle del arqueo</h2>
                <p class="mt-1 text-sm text-muted">{{ $items->count() }} cobro(s) registrado(s).</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head">Método</th>
                        <th class="table-head">Referencia</th>
                        <th class="table-head">Cobro</th>
                        <th class="table-head text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($items as $item)
                        <tr wire:key="cash-item-{{ $item->id }}">
                            <td class="table-cell">{{ $paymentMethods[$item->payment_method->value] ?? $item->payment_method->value }}</td>
                            <td class="table-cell text-muted">{{ $item->reference ?: '—' }}</td>
                            <td class="table-cell whitespace-nowrap text-muted">
                                @if ($item->collectionEvent)
                                    {{ $item->collectionEvent->collected_on?->format('d/m/Y') }}
                                    · {{ $money($item->collectionEvent->amount) }} {{ $item->collectionEvent->currency }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="table-cell whitespace-nowrap text-right font-semibold text-ink">{{ $money($item->amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-muted">Aún no hay cobros en este arqueo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage && $isOpen)
        <section class="card p-4 sm:p-6">
            <h2 class="text-base font-semibold text-ink">Agregar cobro</h2>
            <p class="mt-1 text-sm text-muted">Registra el efectivo, cheque o transferencia recibida.</p>

            <form wire:submit="addItem" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="item-payment-method" class="label">Método de pago</label>
                    <select id="item-payment-method" wire:model="itemPaymentMethod"
                        class="select">
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="item-amount" class="label">Monto</label>
                    <input id="item-amount" type="number" step="0.01" min="0" wire:model="itemAmount" inputmode="decimal"
                        class="input">
                    @error('amount')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="item-reference" class="label">Referencia (opcional)</label>
                    <input id="item-reference" type="text" wire:model="itemReference" maxlength="255"
                        class="input">
                    @error('reference')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="item-collection" class="label">Cobro pendiente (opcional)</label>
                    <select id="item-collection" wire:model="itemCollectionEventId"
                        class="select">
                        <option value="">Sin cobro asociado</option>
                        @foreach ($availableCollections as $collection)
                            <option value="{{ $collection->id }}">
                                {{ $collection->collected_on?->format('d/m/Y') }} · {{ $collection->invoice?->number }} · {{ $collection->invoice?->customer?->name }} · {{ $money($collection->amount) }} {{ $collection->currency }}
                            </option>
                        @endforeach
                    </select>
                    @error('collection_event_id')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="btn btn-dark w-full sm:w-auto">
                        Agregar cobro
                    </button>
                </div>
            </form>
        </section>

        <section class="card p-4 sm:p-6">
            <h2 class="text-base font-semibold text-ink">Enviar arqueo</h2>
            <p class="mt-1 text-sm text-muted">Indica el monto contado para calcular la diferencia.</p>

            <form wire:submit="submit" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="counted-amount" class="label">Monto contado</label>
                    <input id="counted-amount" type="number" step="0.01" min="0" wire:model="countedAmount" inputmode="decimal"
                        class="input">
                    @error('counted_amount')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="cash-notes" class="label">Notas (opcional)</label>
                    <textarea id="cash-notes" rows="2" wire:model="notes"
                        class="input"></textarea>
                    @error('notes')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="btn btn-dark w-full sm:w-auto">
                        Enviar arqueo
                    </button>
                </div>
            </form>
        </section>
    @endif

    @if ($canManage && $isRejected)
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 sm:p-6">
            <h2 class="text-base font-semibold text-ink">Arqueo rechazado</h2>
            <p class="mt-1 text-sm text-muted">Corrige los cobros o vuelve a enviar el arqueo.</p>

            <button type="button" wire:click="reopen" wire:loading.attr="disabled"
                class="mt-4 btn btn-dark w-full sm:w-auto">
                Reabrir arqueo
            </button>
        </section>
    @endif

    @if ($canApprove && $isSubmitted)
        <section class="card p-4 sm:p-6">
            <h2 class="text-base font-semibold text-ink">Revisión</h2>
            <p class="mt-1 text-sm text-muted">Aprueba el arqueo o recházalo indicando el motivo.</p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-3">
                <button type="button" wire:click="approve" wire:loading.attr="disabled"
                    class="btn btn-dark w-full sm:w-auto">
                    Aprobar arqueo
                </button>
            </div>

            <form wire:submit="reject" class="mt-5 space-y-3">
                <div>
                    <label for="reject-reason" class="label">Motivo del rechazo</label>
                    <textarea id="reject-reason" rows="3" wire:model="rejectReason"
                        class="input"></textarea>
                    @error('rejectReason')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="btn btn-danger w-full sm:w-auto">
                    Rechazar arqueo
                </button>
            </form>
        </section>
    @endif
</div>
