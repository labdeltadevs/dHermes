<?php

use App\Actions\Collection\RegisterCollection;
use App\Enums\CollectionKind;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Invoice;
use App\Queries\SellerPortfolioQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Registrar cobro')] class extends Component {
    use WithFileUploads;

    public string $invoiceSearch = '';

    public string $invoiceId = '';

    public string $amount = '';

    public string $currency = '';

    public string $paymentMethod = PaymentMethod::Cash->value;

    public string $collectedOn = '';

    public string $kind = CollectionKind::Full->value;

    public string $localAmount = '';

    public string $notes = '';

    public string $voucherCode = '';

    public ?TemporaryUploadedFile $proof = null;

    public bool $confirmed = false;

    public bool $embedded = false;

    public function mount(?string $invoice = null, bool $embedded = false): void
    {
        $this->embedded = $embedded;

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
        $this->voucherCode = '';
        $this->proof = null;
        $this->confirmed = false;
        $this->resetValidation();
    }

    public function changeInvoice(): void
    {
        $this->reset(['invoiceId', 'amount', 'currency', 'paymentMethod', 'collectedOn', 'kind', 'localAmount', 'notes', 'voucherCode', 'proof', 'confirmed']);
        $this->resetValidation();
        Gate::authorize('viewAny', Invoice::class);
    }

    public function save(): void
    {
        $paymentMethod = PaymentMethod::from($this->paymentMethod);

        $this->validate(
            [
                'invoiceId' => ['required', 'string'],
                'voucherCode' => ['nullable', 'string', 'max:64', Rule::requiredIf($paymentMethod !== PaymentMethod::Cash)],
                'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
                'confirmed' => ['accepted'],
            ],
            [
                'invoiceId.required' => 'Selecciona una factura para registrar el cobro.',
                'voucherCode.required' => 'El código de voucher es obligatorio para este método de pago.',
                'confirmed.accepted' => 'Debes confirmar la información antes de registrar el cobro.',
            ],
        );

        $user = auth()->user();
        $invoice = Invoice::query()->find($this->invoiceId);

        abort_if($invoice === null, 404);

        Gate::authorize('create', [CollectionEvent::class, $invoice]);

        $collection = app(RegisterCollection::class)->handle($user, $invoice, [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'local_amount' => $this->localAmount === '' ? null : $this->localAmount,
            'payment_method' => $this->paymentMethod,
            'collected_on' => $this->collectedOn,
            'kind' => $this->kind,
            'notes' => $this->notes === '' ? null : $this->notes,
            'voucher_code' => $this->voucherCode === '' ? null : $this->voucherCode,
            'proof' => $this->proof,
        ]);

        if ($this->embedded) {
            $this->dispatch('collection-created', collectionId: $collection->id);

            return;
        }

        session()->flash('status', 'Cobro registrado correctamente.');
        $this->redirectRoute('collections.index');
    }

    public function render()
    {
        $user = auth()->user();
        $membership = $user->currentMembership();

        abort_if($membership === null, 403);

        $invoice =
            $this->invoiceId !== ''
                ? Invoice::query()
                    ->with(['customer', 'salesRep'])
                    ->find($this->invoiceId)
                : null;

        $canCreate = $membership->role->sells();

        $invoices =
            $invoice === null && $canCreate
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
    $money = fn(string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
@endphp

<div class="space-y-5 sm:space-y-6">
    @if (!$embedded)
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <x-breadcrumb :items="[
                    ['label' => 'Inicio', 'url' => route('dashboard')],
                    ['label' => 'Cobranzas', 'url' => route('collections.index')],
                    ['label' => 'Registrar cobro'],
                ]" />
                <h1 class="page-title">Registrar cobro</h1>
                <p class="page-subtitle">Informa un cobro para una factura abierta de tu cartera.</p>
            </div>

            <a href="{{ route('collections.index') }}" wire:navigate class="btn btn-outline">
                Volver a cobranzas
            </a>
        </section>
    @endif

    @if ($invoice === null)
        <section class="card p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-ink">Selecciona una factura</h2>
                    <p class="mt-1 text-sm text-muted">Solo se muestran facturas abiertas a las que tienes acceso.</p>
                </div>

                <div class="w-full sm:max-w-sm">
                    <label for="invoice-search" class="label">Buscar factura o cliente</label>
                    <input id="invoice-search" type="search" wire:model.live.debounce.400ms="invoiceSearch"
                        placeholder="Número, documento, cliente" class="input">
                </div>
            </div>

            @if (!$canCreate)
                <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Tu rol no permite registrar cobros.
                </p>
            @else
                <div class="mt-5 space-y-3" aria-label="Facturas disponibles">
                    @forelse ($invoices as $option)
                        <button type="button" wire:key="invoice-option-{{ $option->id }}"
                            wire:click="selectInvoice('{{ $option->id }}')"
                            class="flex w-full flex-col gap-3 rounded-xl border border-line bg-surface p-4 text-left transition hover:border-brand-300 hover:bg-brand-50/40 sm:flex-row sm:items-center sm:justify-between">
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-semibold text-ink">{{ $option->customer?->name }}</span>
                                <span class="mt-0.5 block text-xs text-muted">{{ $option->number }} ·
                                    {{ $option->customer?->cardcode }} · Vence
                                    {{ $option->due_date?->format('d/m/Y') }}</span>
                            </span>
                            <span class="shrink-0 text-left sm:text-right">
                                <span class="block text-sm font-semibold text-ink">{{ $option->currency }}
                                    {{ $money($option->balance) }}</span>
                                <span class="mt-0.5 block text-xs text-muted">Saldo pendiente</span>
                            </span>
                        </button>
                    @empty
                        <div class="empty-state">
                            <p class="text-sm font-medium text-ink">No hay facturas abiertas con estos criterios.</p>
                        </div>
                    @endforelse
                </div>
            @endif
        </section>
    @else
        <section class="card p-4 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="stat-label">Factura seleccionada</p>
                    <h2 class="mt-1 text-lg font-semibold text-ink">{{ $invoice->number }} ·
                        {{ $invoice->customer?->name }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ $invoice->docentry }} · {{ $invoice->customer?->cardcode }}
                    </p>
                </div>

                <button type="button" wire:click="changeInvoice"
                    class="text-sm font-medium text-brand-700 transition hover:text-brand-800">
                    Cambiar factura
                </button>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-canvas p-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-muted">Cliente</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->customer?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Vendedor</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->salesRep?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Moneda</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->currency }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Saldo pendiente</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $invoice->currency }} {{ $money($invoice->balance) }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="card p-4 sm:p-6">
            <h2 class="text-base font-semibold text-ink">Datos del cobro</h2>

            <form wire:submit="save" class="mt-5 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="collection-amount" class="label">Monto</label>
                        <input id="collection-amount" type="number" step="0.000001" min="0" inputmode="decimal"
                            wire:model="amount" required class="input">
                        @error('amount')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-currency" class="label">Moneda</label>
                        <input id="collection-currency" type="text" wire:model="currency" readonly class="input">
                        @error('currency')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-method" class="label">Método de pago</label>
                        <select id="collection-method" wire:model="paymentMethod" required class="select">
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="transfer">Transferencia</option>
                            <option value="other">Otro</option>
                        </select>
                        @error('payment_method')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-date" class="label">Fecha de cobro</label>
                        <input id="collection-date" type="date" max="{{ $today }}" wire:model="collectedOn"
                            required class="input">
                        @error('collected_on')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-kind" class="label">Tipo de cobro</label>
                        <select id="collection-kind" wire:model="kind" required class="select">
                            <option value="full">Total</option>
                            <option value="partial">Parcial</option>
                        </select>
                        @error('kind')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-local-amount" class="label">Monto en moneda local (opcional)</label>
                        <input id="collection-local-amount" type="number" step="0.000001" min="0"
                            inputmode="decimal" wire:model="localAmount" class="input">
                        @error('local_amount')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-voucher" class="label">Código de voucher</label>
                        <input id="collection-voucher" type="text" maxlength="64" wire:model="voucherCode"
                            placeholder="Obligatorio para cheque, transferencia u otro" class="input">
                        @error('voucherCode')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                        @error('voucher_code')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="collection-proof" class="label">Comprobante (opcional)</label>
                        <input id="collection-proof" type="file" wire:model="proof"
                            accept=".jpg,.jpeg,.png,.webp,.pdf" class="input">
                        <p class="mt-1 text-xs text-muted">Formatos jpg, png, webp o pdf. Máximo 5 MB.</p>
                        @error('proof')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="collection-notes" class="label">Notas (opcional)</label>
                        <textarea id="collection-notes" rows="4" maxlength="1000" wire:model="notes" class="input"></textarea>
                        @error('notes')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-xl border border-line bg-canvas p-4">
                    <label for="collection-confirmed" class="flex items-start gap-3 text-sm text-ink">
                        <input id="collection-confirmed" type="checkbox" wire:model="confirmed"
                            class="mt-0.5 h-4 w-4 rounded border-line text-brand-700 focus:ring-brand-500">
                        <span>
                            Confirmo que los datos del cobro son correctos y que adjunté el comprobante cuando
                            correspondia.
                        </span>
                    </label>
                    @error('confirmed')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" class="btn btn-dark w-full sm:w-auto">
                    Registrar cobro
                </button>
            </form>
        </section>
    @endif
</div>
