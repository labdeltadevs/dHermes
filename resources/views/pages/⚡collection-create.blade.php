<?php

use App\Actions\Collection\RegisterCollection;
use App\Enums\CollectionKind;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Queries\SellerPortfolioQuery;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Registrar cobro')] class extends Component
{
    public string $invoiceSearch = '';

    public string $invoiceId = '';

    public string $amount = '';

    public string $currency = '';

    public string $paymentMethod = PaymentMethod::Cash->value;

    public string $collectedOn = '';

    public string $kind = CollectionKind::Full->value;

    public string $localAmount = '';

    public string $notes = '';

    public function mount(?string $invoice = null): void
    {
        $invoiceId = $invoice ?? request()->query('invoice');

        if (is_string($invoiceId) && $invoiceId !== '') {
            $this->selectInvoice($invoiceId);

            return;
        }

        Gate::authorize('viewAny', Invoice::class);
    }

    public function selectInvoice(string $invoiceId): void
    {
        $user = auth()->user();
        $invoice = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->with(['customer', 'salesRep'])
            ->find($invoiceId);

        abort_if($invoice === null, 404);
        abort_unless($invoice->tenant_id === $user->currentTenantId(), 403);

        Gate::authorize('create', [CollectionEvent::class, $invoice]);

        $this->invoiceId = $invoice->id;
        $this->amount = (string) $invoice->balance;
        $this->currency = $invoice->currency;
        $this->paymentMethod = PaymentMethod::Cash->value;
        $this->collectedOn = now()->toDateString();
        $this->kind = CollectionKind::Full->value;
        $this->localAmount = '';
        $this->notes = '';
        $this->resetValidation();
    }

    public function changeInvoice(): void
    {
        $this->reset(['invoiceId', 'amount', 'currency', 'paymentMethod', 'collectedOn', 'kind', 'localAmount', 'notes']);
        $this->resetValidation();
        Gate::authorize('viewAny', Invoice::class);
    }

    public function save(): void
    {
        $this->validate([
            'invoiceId' => ['required', 'string'],
        ], [
            'invoiceId.required' => 'Selecciona una factura para registrar el cobro.',
        ]);

        $user = auth()->user();
        $invoice = Invoice::query()->find($this->invoiceId);

        abort_if($invoice === null, 404);

        Gate::authorize('create', [CollectionEvent::class, $invoice]);

        app(RegisterCollection::class)->handle($user, $invoice, [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'local_amount' => $this->localAmount === '' ? null : $this->localAmount,
            'payment_method' => $this->paymentMethod,
            'collected_on' => $this->collectedOn,
            'kind' => $this->kind,
            'notes' => $this->notes === '' ? null : $this->notes,
        ]);

        session()->flash('status', 'Cobro registrado correctamente.');
        $this->redirectRoute('collections.index');
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $invoice = $this->invoiceId !== ''
            ? Invoice::query()->with(['customer', 'salesRep'])->find($this->invoiceId)
            : null;

        $canCreate = $membership->role->sells();

        $invoices = $invoice === null && $canCreate
            ? app(SellerPortfolioQuery::class)
                ->filtered($user, ['search' => $this->invoiceSearch, 'sort' => 'due_asc'])
                ->limit(20)
                ->get()
            : collect();

        return $this->view()->with([
            'invoice' => $invoice,
            'invoices' => $invoices,
            'canCreate' => $canCreate,
            'today' => now()->toDateString(),
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
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Registrar cobro</h1>
            <p class="mt-1.5 text-sm text-black/60">Informa un cobro para una factura abierta de tu cartera.</p>
        </div>

        <a href="{{ route('collections.index') }}" wire:navigate
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
            Volver a cobranzas
        </a>
    </section>

    @if ($invoice === null)
        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-ink">Selecciona una factura</h2>
                    <p class="mt-1 text-sm text-black/60">Solo se muestran facturas abiertas a las que tienes acceso.</p>
                </div>

                <div class="w-full sm:max-w-sm">
                    <label for="invoice-search" class="mb-1.5 block text-sm font-medium text-black/70">Buscar factura o cliente</label>
                    <input id="invoice-search" type="search" wire:model.live.debounce.400ms="invoiceSearch"
                        placeholder="Número, documento, cliente"
                        class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                </div>
            </div>

            @if (! $canCreate)
                <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Tu rol no permite registrar cobros.
                </p>
            @else
                <div class="mt-5 space-y-3" aria-label="Facturas disponibles">
                    @forelse ($invoices as $option)
                        <button type="button" wire:key="invoice-option-{{ $option->id }}" wire:click="selectInvoice('{{ $option->id }}')"
                            class="flex w-full flex-col gap-3 rounded-xl border border-black/10 bg-white p-4 text-left transition hover:border-brand-300 hover:bg-brand-50/40 sm:flex-row sm:items-center sm:justify-between">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $option->customer?->name }}</span>
                                <span class="mt-0.5 block text-xs text-black/60">{{ $option->number }} · {{ $option->customer?->cardcode }} · Vence {{ $option->due_date?->format('d/m/Y') }}</span>
                            </span>
                            <span class="shrink-0 text-left sm:text-right">
                                <span class="block text-sm font-semibold text-ink">{{ $option->currency }} {{ $money($option->balance) }}</span>
                                <span class="mt-0.5 block text-xs text-black/60">Saldo pendiente</span>
                            </span>
                        </button>
                    @empty
                        <div class="rounded-xl border border-dashed border-black/15 px-4 py-10 text-center">
                            <p class="text-sm font-medium text-ink">No hay facturas abiertas con estos criterios.</p>
                        </div>
                    @endforelse
                </div>
            @endif
        </section>
    @else
        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-black/60">Factura seleccionada</p>
                    <h2 class="mt-1 text-lg font-semibold text-ink">{{ $invoice->number }} · {{ $invoice->customer?->name }}</h2>
                    <p class="mt-1 text-sm text-black/60">{{ $invoice->docentry }} · {{ $invoice->customer?->cardcode }}</p>
                </div>

                <button type="button" wire:click="changeInvoice"
                    class="text-sm font-medium text-brand-700 transition hover:text-brand-800">
                    Cambiar factura
                </button>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-canvas p-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-black/60">Cliente</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->customer?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-black/60">Vendedor</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->salesRep?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-black/60">Moneda</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->currency }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-black/60">Saldo pendiente</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->currency }} {{ $money($invoice->balance) }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-ink">Datos del cobro</h2>

            <form wire:submit="save" class="mt-5 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="collection-amount" class="mb-1.5 block text-sm font-medium text-black/70">Monto</label>
                        <input id="collection-amount" type="number" step="0.000001" min="0" inputmode="decimal" wire:model="amount" required
                            class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        @error('amount')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-currency" class="mb-1.5 block text-sm font-medium text-black/70">Moneda</label>
                        <input id="collection-currency" type="text" wire:model="currency" readonly
                            class="block min-h-11 w-full rounded-xl border border-black/15 bg-canvas px-3.5 py-2.5 text-base text-black/70 outline-none sm:text-sm">
                        @error('currency')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-method" class="mb-1.5 block text-sm font-medium text-black/70">Método de pago</label>
                        <select id="collection-method" wire:model="paymentMethod" required
                            class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="transfer">Transferencia</option>
                            <option value="other">Otro</option>
                        </select>
                        @error('payment_method')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-date" class="mb-1.5 block text-sm font-medium text-black/70">Fecha de cobro</label>
                        <input id="collection-date" type="date" max="{{ $today }}" wire:model="collectedOn" required
                            class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        @error('collected_on')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-kind" class="mb-1.5 block text-sm font-medium text-black/70">Tipo de cobro</label>
                        <select id="collection-kind" wire:model="kind" required
                            class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                            <option value="full">Total</option>
                            <option value="partial">Parcial</option>
                        </select>
                        @error('kind')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-local-amount" class="mb-1.5 block text-sm font-medium text-black/70">Monto en moneda local (opcional)</label>
                        <input id="collection-local-amount" type="number" step="0.000001" min="0" inputmode="decimal" wire:model="localAmount"
                            class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                        @error('local_amount')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="collection-notes" class="mb-1.5 block text-sm font-medium text-black/70">Notas (opcional)</label>
                        <textarea id="collection-notes" rows="4" maxlength="1000" wire:model="notes"
                            class="block w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm"></textarea>
                        @error('notes')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80 disabled:opacity-50 sm:w-auto">
                    Registrar cobro
                </button>
            </form>
        </section>
    @endif
</div>
