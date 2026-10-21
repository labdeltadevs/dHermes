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
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Arqueo de {{ $cashSession->seller?->name ?? 'vendedor' }}</h1>
            <p class="mt-1.5 text-sm text-black/60">Apertura {{ $cashSession->opened_at?->format('d/m/Y H:i') }} · Moneda {{ $cashSession->currency }}</p>
        </div>

        <div class="flex flex-col items-start gap-2 sm:items-end">
            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-sm font-semibold {{ $statusClasses($cashSession->status) }}">
                {{ $statusLabels[$cashSession->status->value] }}
            </span>

            @if ($canPrint)
                <button type="button" wire:click="issueComprobante" wire:loading.attr="disabled"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 px-3.5 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 disabled:opacity-50">
                    Emitir comprobante
                </button>
            @endif
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Esperado</p>
            <p class="mt-2 text-2xl font-semibold text-ink">{{ $money($cashSession->expected_amount) }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Contado</p>
            <p class="mt-2 text-2xl font-semibold text-ink">{{ $cashSession->counted_amount === null ? '—' : $money($cashSession->counted_amount) }}</p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Diferencia</p>
            <p class="mt-2 text-2xl font-semibold {{ (float) $cashSession->difference < 0 ? 'text-rose-700' : 'text-ink' }}">
                {{ $cashSession->difference === null ? '—' : $money($cashSession->difference) }}
            </p>
        </article>

        <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Cierre</p>
            <p class="mt-2 text-sm font-semibold text-ink">{{ $cashSession->closed_at?->format('d/m/Y H:i') ?? 'Sin cerrar' }}</p>
            <p class="mt-1 text-xs text-black/60">Aprobador: {{ $cashSession->approver?->name ?? '—' }}</p>
        </article>
    </section>

    @if ($cashSession->notes)
        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-ink">Notas</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-black/70">{{ $cashSession->notes }}</p>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-black/10 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-ink">Detalle del arqueo</h2>
                <p class="mt-1 text-sm text-black/60">{{ $items->count() }} cobro(s) registrado(s).</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Método</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Referencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Cobro</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($items as $item)
                        <tr wire:key="cash-item-{{ $item->id }}">
                            <td class="px-4 py-4 text-sm text-ink">{{ $paymentMethods[$item->payment_method->value] ?? $item->payment_method->value }}</td>
                            <td class="px-4 py-4 text-sm text-black/70">{{ $item->reference ?: '—' }}</td>
                            <td class="px-4 py-4 text-sm text-black/70">
                                @if ($item->collectionEvent)
                                    {{ $item->collectionEvent->collected_on?->format('d/m/Y') }}
                                    · {{ $money($item->collectionEvent->amount) }} {{ $item->collectionEvent->currency }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-semibold text-ink">{{ $money($item->amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-black/60">Aún no hay cobros en este arqueo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage && $isOpen)
        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-ink">Agregar cobro</h2>
            <p class="mt-1 text-sm text-black/60">Registra el efectivo, cheque o transferencia recibida.</p>

            <form wire:submit="addItem" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="item-payment-method" class="mb-1.5 block text-sm font-medium text-black/70">Método de pago</label>
                    <select id="item-payment-method" wire:model="itemPaymentMethod"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="item-amount" class="mb-1.5 block text-sm font-medium text-black/70">Monto</label>
                    <input id="item-amount" type="number" step="0.01" min="0" wire:model="itemAmount" inputmode="decimal"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    @error('amount')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="item-reference" class="mb-1.5 block text-sm font-medium text-black/70">Referencia (opcional)</label>
                    <input id="item-reference" type="text" wire:model="itemReference" maxlength="255"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    @error('reference')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="item-collection" class="mb-1.5 block text-sm font-medium text-black/70">Cobro pendiente (opcional)</label>
                    <select id="item-collection" wire:model="itemCollectionEventId"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        <option value="">Sin cobro asociado</option>
                        @foreach ($availableCollections as $collection)
                            <option value="{{ $collection->id }}">
                                {{ $collection->collected_on?->format('d/m/Y') }} · {{ $collection->invoice?->number }} · {{ $collection->invoice?->customer?->name }} · {{ $money($collection->amount) }} {{ $collection->currency }}
                            </option>
                        @endforeach
                    </select>
                    @error('collection_event_id')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50 sm:w-auto">
                        Agregar cobro
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-ink">Enviar arqueo</h2>
            <p class="mt-1 text-sm text-black/60">Indica el monto contado para calcular la diferencia.</p>

            <form wire:submit="submit" class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="counted-amount" class="mb-1.5 block text-sm font-medium text-black/70">Monto contado</label>
                    <input id="counted-amount" type="number" step="0.01" min="0" wire:model="countedAmount" inputmode="decimal"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    @error('counted_amount')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="cash-notes" class="mb-1.5 block text-sm font-medium text-black/70">Notas (opcional)</label>
                    <textarea id="cash-notes" rows="2" wire:model="notes"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm"></textarea>
                    @error('notes')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50 sm:w-auto">
                        Enviar arqueo
                    </button>
                </div>
            </form>
        </section>
    @endif

    @if ($canManage && $isRejected)
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-ink">Arqueo rechazado</h2>
            <p class="mt-1 text-sm text-black/70">Corrige los cobros o vuelve a enviar el arqueo.</p>

            <button type="button" wire:click="reopen" wire:loading.attr="disabled"
                class="mt-4 min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50 sm:w-auto">
                Reabrir arqueo
            </button>
        </section>
    @endif

    @if ($canApprove && $isSubmitted)
        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-ink">Revisión</h2>
            <p class="mt-1 text-sm text-black/60">Aprueba el arqueo o recházalo indicando el motivo.</p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-3">
                <button type="button" wire:click="approve" wire:loading.attr="disabled"
                    class="min-h-11 w-full rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50 sm:w-auto">
                    Aprobar arqueo
                </button>
            </div>

            <form wire:submit="reject" class="mt-5 space-y-3">
                <div>
                    <label for="reject-reason" class="mb-1.5 block text-sm font-medium text-black/70">Motivo del rechazo</label>
                    <textarea id="reject-reason" rows="3" wire:model="rejectReason"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm"></textarea>
                    @error('rejectReason')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="min-h-11 w-full rounded-xl border border-rose-200 bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-50 sm:w-auto">
                    Rechazar arqueo
                </button>
            </form>
        </section>
    @endif
</div>
