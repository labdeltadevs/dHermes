<?php

use App\Actions\Collection\RegisterFlashCollection;
use App\Enums\PaymentMethod;
use App\Models\CollectionEvent;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Collection\CardcodeNormalizer;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Cobro flash')] class extends Component {
    use WithFileUploads;

    public string $amount = '';

    public string $flashInvoiceNumber = '';

    public string $flashCardcode = '';

    public string $flashCustomerName = '';

    public string $paymentMethod = PaymentMethod::Cash->value;

    public string $collectedOn = '';

    public string $notes = '';

    public string $voucherCode = '';

    public ?TemporaryUploadedFile $proof = null;

    public bool $confirmed = false;

    /** @var list<array{cardcode: string, name: string}> */
    public array $customerMatches = [];

    public ?string $matchedCustomerName = null;

    public function mount(): void
    {
        Gate::authorize('createFlash', CollectionEvent::class);

        $this->collectedOn = now()->toDateString();
    }

    public function getNormalizedCardcodeProperty(): string
    {
        return CardcodeNormalizer::normalize($this->flashCardcode);
    }

    public function updatedFlashCardcode(): void
    {
        $this->lookupCustomer();
    }

    public function lookupCustomer(): void
    {
        $this->customerMatches = [];
        $this->matchedCustomerName = null;

        $normalized = $this->normalizedCardcode;

        if ($normalized === '') {
            return;
        }

        $exact = $this->customersMatching($normalized, exact: true);

        if ($exact->count() === 1) {
            $customer = $exact->first();

            $this->flashCustomerName = (string) $customer->name;
            $this->matchedCustomerName = (string) $customer->name;

            return;
        }

        $candidates = $exact->isNotEmpty()
            ? $exact
            : $this->customersMatching($normalized, exact: false);

        $this->customerMatches = $candidates
            ->take(5)
            ->map(fn (Customer $customer): array => [
                'cardcode' => (string) $customer->cardcode,
                'name' => (string) $customer->name,
            ])
            ->values()
            ->all();
    }

    public function selectMatch(string $cardcode): void
    {
        $match = collect($this->customerMatches)->firstWhere('cardcode', $cardcode);

        if (! is_array($match)) {
            return;
        }

        $this->flashCardcode = (string) $match['cardcode'];
        $this->flashCustomerName = (string) $match['name'];
        $this->matchedCustomerName = (string) $match['name'];
        $this->customerMatches = [];
    }

    /**
     * Busca clientes del tenant comparando el CardCode normalizado.
     *
     * @return Collection<int, Customer>
     */
    private function customersMatching(string $normalized, bool $exact): Collection
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $expression = "REGEXP_REPLACE(UPPER(cardcode), '[^A-Z0-9]', '', 'g')";

            return Customer::query()
                ->when(
                    $exact,
                    fn ($query) => $query->whereRaw("{$expression} = ?", [$normalized]),
                    fn ($query) => $query->whereRaw(
                        "{$expression} LIKE ? OR ? LIKE '%' || {$expression} || '%'",
                        ["%{$normalized}%", $normalized],
                    ),
                )
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'cardcode', 'name']);
        }

        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'cardcode', 'name'])
            ->filter(function (Customer $customer) use ($normalized, $exact): bool {
                $cardcode = CardcodeNormalizer::normalize((string) $customer->cardcode);

                return $exact
                    ? $cardcode === $normalized
                    : str_contains($cardcode, $normalized) || str_contains($normalized, $cardcode);
            })
            ->values();
    }

    public function save(): void
    {
        $paymentMethod = PaymentMethod::from($this->paymentMethod);

        $this->flashCardcode = $this->normalizedCardcode;

        $this->validate(
            [
                'amount' => ['required', 'numeric', 'gt:0'],
                'flashInvoiceNumber' => ['required', 'string', 'max:255'],
                'flashCardcode' => ['required', 'string', 'max:32'],
                'flashCustomerName' => ['required', 'string', 'max:255'],
                'voucherCode' => ['nullable', 'string', 'max:64', Rule::requiredIf($paymentMethod !== PaymentMethod::Cash && $paymentMethod !== PaymentMethod::Qr)],
                'collectedOn' => ['required', 'date', 'before_or_equal:today'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
                'confirmed' => ['accepted'],
            ],
            [
                'amount.required' => 'Ingresa el monto del cobro.',
                'amount.gt' => 'El monto debe ser mayor a cero.',
                'flashInvoiceNumber.required' => 'Ingresa el número de factura.',
                'flashCardcode.required' => 'Ingresa el código del cliente.',
                'flashCustomerName.required' => 'Ingresa el nombre del cliente.',
                'voucherCode.required' => 'El código de voucher es obligatorio para este método de pago.',
                'collectedOn.before_or_equal' => 'La fecha de cobro no puede ser posterior a hoy.',
                'confirmed.accepted' => 'Debes confirmar que la información es verdadera antes de registrar el cobro.',
            ],
        );

        $user = auth()->user();

        abort_if($user === null, 403);

        app(RegisterFlashCollection::class)->handle($user, [
            'amount' => $this->amount,
            'flash_invoice_number' => $this->flashInvoiceNumber,
            'flash_cardcode' => $this->flashCardcode,
            'flash_customer_name' => $this->flashCustomerName,
            'payment_method' => $this->paymentMethod,
            'collected_on' => $this->collectedOn,
            'notes' => $this->notes === '' ? null : $this->notes,
            'voucher_code' => $this->voucherCode === '' ? null : $this->voucherCode,
            'proof' => $this->proof,
        ]);

        session()->flash('status', 'Cobro flash registrado correctamente.');
        $this->redirectRoute('collections.index');
    }

    public function render()
    {
        $tenant = Tenant::query()->find(app(CurrentTenant::class)->requireId());

        return $this->view()->with([
            'currency' => $tenant?->local_currency ?? '—',
            'today' => now()->toDateString(),
        ]);
    }
};
?>

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-breadcrumb :items="[
                ['label' => 'Inicio', 'url' => route('dashboard')],
                ['label' => 'Cobranzas', 'url' => route('collections.index')],
                ['label' => 'Cobro flash'],
            ]" />
            <h1 class="page-title">Cobro flash</h1>
            <p class="page-subtitle">
                Registra un cobro cuya factura aún no está en la cartera. Quedará en revisión para su aprobación.
            </p>
        </div>

        <a href="{{ route('collections.index') }}" wire:navigate class="btn btn-outline">
            Volver a cobranzas
        </a>
    </section>

    <section class="card p-4 sm:p-6">
        <h2 class="text-base font-semibold text-ink">Datos del cobro</h2>
        <p class="mt-1 text-sm text-muted">
            El cobro se registra con la moneda de la empresa ({{ $currency }}).
        </p>

        <form wire:submit="save" class="mt-5 space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="flash-amount" class="label">Monto</label>
                    <input id="flash-amount" type="number" step="0.000001" min="0" inputmode="decimal"
                        wire:model="amount" required class="input">
                    @error('amount')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-currency" class="label">Moneda</label>
                    <input id="flash-currency" type="text" value="{{ $currency }}" readonly tabindex="-1"
                        aria-readonly="true" class="input cursor-not-allowed bg-canvas text-muted">
                </div>

                <div>
                    <label for="flash-invoice-number" class="label">Número de factura</label>
                    <input id="flash-invoice-number" type="text" maxlength="255" wire:model="flashInvoiceNumber"
                        placeholder="Factura a la que corresponde el pago" required class="input">
                    <p class="mt-1.5 text-xs text-muted">
                        Varias facturas: sepáralas con coma (00911, 00912, 00913, 00915).
                    </p>
                    @error('flashInvoiceNumber')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-cardcode" class="label">Código de cliente</label>
                    <input id="flash-cardcode" type="text" maxlength="32"
                        wire:model.live.debounce.400ms="flashCardcode" placeholder="Código del cliente" required
                        class="input">
                    <p class="mt-1.5 text-xs text-muted">
                        Buscar coincidencias en la cartera. Si no hay coincidencias, se guardará el código ingresado.
                    </p>
                    @error('flashCardcode')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-cardcode-clean" class="label">Código normalizado (autogenerado por el sistema)</label>
                    <input id="flash-cardcode-clean" type="text" value="{{ $this->normalizedCardcode }}" readonly
                        tabindex="-1" aria-readonly="true" class="input cursor-not-allowed bg-canvas text-muted">
                    @if ($matchedCustomerName !== null)
                        <p class="mt-1.5 text-xs font-medium text-emerald-700">
                            Cliente registrado: {{ $matchedCustomerName }}
                        </p>
                    @elseif (count($customerMatches) > 1)
                        <p class="mt-1.5 text-xs text-amber-700">
                            {{ count($customerMatches) }} coincidencias. Elige el cliente correcto abajo.
                        </p>
                    @elseif ($this->normalizedCardcode !== '')
                        <p class="mt-1.5 text-xs text-muted">
                            Sin coincidencia en la cartera. Se guardará este código.
                        </p>
                    @endif
                </div>

                @if (count($customerMatches) > 0)
                    <div class="sm:col-span-2">
                        <p class="label">Coincidencias en la cartera</p>
                        <ul class="mt-1 divide-y divide-line overflow-hidden rounded-xl border border-line">
                            @foreach ($customerMatches as $match)
                                <li>
                                    <button type="button" wire:click="selectMatch(@js($match['cardcode']))"
                                        class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-sm hover:bg-canvas">
                                        <span class="font-mono text-xs text-muted">{{ $match['cardcode'] }}</span>
                                        <span class="font-medium text-ink">{{ $match['name'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label for="flash-customer-name" class="label">Nombre del cliente</label>
                    <input id="flash-customer-name" type="text" maxlength="255" wire:model="flashCustomerName"
                        placeholder="Nombre o razón social" required class="input">
                    @error('flashCustomerName')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-method" class="label">Tipo de pago</label>
                    <select id="flash-method" wire:model="paymentMethod" required class="select">
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                    @error('paymentMethod')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-collected-on" class="label">Fecha de cobro</label>
                    <input id="flash-collected-on" type="date" max="{{ $today }}" wire:model="collectedOn"
                        required class="input">
                    @error('collectedOn')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-voucher" class="label">N° voucher / transacción</label>
                    <input id="flash-voucher" type="text" maxlength="64" wire:model="voucherCode"
                        placeholder="Opcional. Obligatorio para depósito-transferencia, cheque u otros" class="input">
                    @error('voucherCode')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    @error('voucher_code')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="flash-proof" class="label">Fotografía del comprobante</label>
                    <input id="flash-proof" type="file" wire:model="proof"
                        accept=".jpg,.jpeg,.png,.webp,.pdf" class="input">
                    <p class="mt-1 text-xs text-muted">Formatos jpg, png, webp o pdf. Máximo 5 MB.</p>
                    @error('proof')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="flash-notes" class="label">Notas (opcional)</label>
                    <textarea id="flash-notes" rows="4" maxlength="1000" wire:model="notes" class="input"></textarea>
                    @error('notes')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-xl border border-line bg-canvas p-4">
                <label for="flash-confirmed" class="flex items-start gap-3 text-sm text-ink">
                    <input id="flash-confirmed" type="checkbox" wire:model="confirmed"
                        class="mt-0.5 h-4 w-4 rounded border-line text-brand-700 focus:ring-brand-500">
                    <span>
                        Confirmo que toda la información llenada en este formulario es verdadera y será sujeta a
                        auditoría.
                    </span>
                </label>
                @error('confirmed')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" class="btn btn-dark w-full sm:w-auto">
                Registrar cobro flash
            </button>
        </form>
    </section>
</div>
