<?php

use App\Actions\Cash\AddCashSessionItem;
use App\Actions\Cash\FinishCashSession;
use App\Models\CashSession;
use App\Models\CashSessionItem;
use App\Policies\CashSessionPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public CashSession $session;

    #[Rule('required|string')]
    public string $collectionNumber = '';

    #[Rule('required|numeric|min:0.01')]
    public float $amount = 0;

    #[Rule('required|string')]
    public string $notes = '';

    public array $items = [];

    public bool $showModal = false;

    public function mount(CashSession $session): void
    {
        $this->session = $session;
        $this->loadItems();
    }

    private function loadItems(): void
    {
        $this->items = CashSessionItem::query()
            ->where('cash_session_id', $this->session->id)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function openModal(): void
    {
        abort_if(! CashSessionPolicy::canManage(Auth::user(), $this->session), 403);
        abort_if($this->session->status !== 'open', 422, 'La sesion debe estar abierta.');

        $this->resetValidation();
        $this->collectionNumber = '';
        $this->amount = 0;
        $this->notes = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->validate();

        try {
            app(AddCashSessionItem::class)->execute(
                tenantId: $this->session->tenant_id,
                cashSessionId: $this->session->id,
                collectionNumber: $this->collectionNumber,
                amount: $this->amount,
                notes: $this->notes,
                userId: Auth::id(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->closeModal();
        $this->loadItems();
        $this->dispatch('item-added');
    }

    public function finish(): void
    {
        abort_if(! CashSessionPolicy::canManage(Auth::user(), $this->session), 403);

        try {
            app(FinishCashSession::class)->execute($this->session, Auth::id());
        } catch (\Throwable $e) {
            $this->addError('finish', $e->getMessage());

            return;
        }

        $this->dispatch('session-finished');
        $this->redirect(route('cash-sessions.index'));
    }
}
?>
<section class="max-w-4xl mx-auto px-4 py-6">
    <header class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-[#ff671f]">Items de arqueo</h2>
        <div class="flex gap-2">
            <button wire:click="openModal" class="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-medium hover:opacity-90">
                Nuevo item
            </button>
            <button wire:click="finish" class="rounded-lg bg-neutral-900 text-white px-4 py-2 text-sm font-medium hover:opacity-90">
                Cerrar sesion
            </button>
        </div>
    </header>

    <div class="mt-4 rounded-xl border bg-white dark:bg-neutral-950 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-neutral-100 dark:bg-neutral-900 text-neutral-700 dark:text-neutral-300">
                <tr>
                    <th class="text-left px-4 py-2 font-medium">Numero</th>
                    <th class="text-left px-4 py-2 font-medium">Monto</th>
                    <th class="text-left px-4 py-2 font-medium">Notas</th>
                    <th class="text-left px-4 py-2 font-medium">Creado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $item['collection_number'] }}</td>
                        <td class="px-4 py-2">{{ number_format((float)$item['amount'], 2) }}</td>
                        <td class="px-4 py-2">{{ $item['notes'] }}</td>
                        <td class="px-4 py-2">{{ \Carbon\Carbon::parse($item['created_at'])->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-neutral-500">Sin items registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-neutral-950 rounded-xl p-6 w-full max-w-md space-y-4">
                <h3 class="text-lg font-semibold">Agregar item</h3>
                <div>
                    <label class="text-sm text-neutral-600">Numero de cobro</label>
                    <input wire:model="collectionNumber" class="mt-1 w-full rounded-lg border px-3 py-2" />
                    @error('collectionNumber') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-neutral-600">Monto</label>
                    <input type="number" step="0.01" wire:model="amount" class="mt-1 w-full rounded-lg border px-3 py-2" />
                    @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-neutral-600">Notas</label>
                    <input wire:model="notes" class="mt-1 w-full rounded-lg border px-3 py-2" />
                    @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm">Cancelar</button>
                    <button wire:click="save" class="rounded-lg bg-[#ff671f] text-white px-4 py-2 text-sm">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</section>
