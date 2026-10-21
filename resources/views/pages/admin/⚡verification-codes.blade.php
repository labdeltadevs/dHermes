<?php

use App\Models\CashSession;
use App\Models\CollectionEvent;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Verification\RevokeVerificationCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Comprobantes')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', VerificationCode::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function revoke(string $codeId): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $code = VerificationCode::query()->whereKey($codeId)->firstOrFail();

        app(RevokeVerificationCode::class)->handle($user, $code);

        session()->flash('status', 'Comprobante revocado.');
    }

    public function render()
    {
        $search = trim($this->search);

        return $this->view()->with([
            'codes' => VerificationCode::query()
                ->with('verifiable')
                ->when($search !== '', fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('verifiable_id', 'like', '%'.$search.'%')
                ))
                ->when($this->status === 'active', fn (Builder $query): Builder => $query->whereNull('revoked_at'))
                ->when($this->status === 'revoked', fn (Builder $query): Builder => $query->whereNotNull('revoked_at'))
                ->orderByDesc('issued_at')
                ->paginate(25)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }
};
?>

@php
$money = fn (string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
$typeLabel = fn (VerificationCode $code): string => match ($code->verifiable_type) {
    CollectionEvent::class => 'Cobranza',
    CashSession::class => 'Arqueo',
    default => class_basename($code->verifiable_type),
};
$subjectSummary = function (VerificationCode $code) use ($money): string {
    $subject = $code->verifiable;

    if ($subject instanceof CollectionEvent) {
        return $subject->currency.' '.$money($subject->amount);
    }

    if ($subject instanceof CashSession) {
        return $subject->currency.' '.$money($subject->expected_amount);
    }

    return '—';
};
@endphp

<div class="space-y-5 sm:space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Administración</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Comprobantes</h1>
            <p class="mt-1.5 text-sm text-black/60">Consulta, imprime y revoca los códigos de verificación emitidos.</p>
        </div>

        @if ($search !== '' || $status !== '')
            <button type="button" wire:click="resetFilters"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                Limpiar filtros
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
            <div>
                <label for="verification-search" class="mb-1.5 block text-sm font-medium text-black/70">Buscar</label>
                <input id="verification-search" type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Código o identificador del comprobante"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="verification-status" class="mb-1.5 block text-sm font-medium text-black/70">Estado</label>
                <select id="verification-status" wire:model.live="status"
                    class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                    <option value="">Todos</option>
                    <option value="active">Activos</option>
                    <option value="revoked">Revocados</option>
                </select>
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Código</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Resumen</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Emitido</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Estado</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-black/60">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($codes as $code)
                        <tr wire:key="verification-code-{{ $code->id }}">
                            <td class="px-4 py-4">
                                <p class="max-w-48 truncate font-mono text-xs font-semibold text-ink">{{ $code->code }}</p>
                                <p class="mt-1 max-w-48 truncate text-xs text-black/60">{{ $code->verifiable_id }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $typeLabel($code) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $subjectSummary($code) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">{{ $code->issued_at?->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($code->isRevoked())
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Revocado</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                <a href="{{ route('verification.show', $code->code) }}" target="_blank" rel="noopener"
                                    class="font-medium text-brand-700 hover:text-brand-800">Ver</a>
                                <a href="{{ route('verification.print', $code->code) }}" target="_blank" rel="noopener"
                                    class="ml-3 font-medium text-brand-700 hover:text-brand-800">Imprimir</a>
                                @if (! $code->isRevoked())
                                    @can('revoke', $code)
                                        <button type="button" wire:click="revoke('{{ $code->id }}')"
                                            wire:confirm="¿Revocar este comprobante? Esta acción no se puede deshacer."
                                            class="ml-3 font-medium text-rose-700 hover:text-rose-800">Revocar</button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-black/60">No hay comprobantes que coincidan con los filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Listado de comprobantes">
        @forelse ($codes as $code)
            <article wire:key="verification-code-card-{{ $code->id }}" class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-mono text-xs font-semibold text-ink">{{ $code->code }}</p>
                        <p class="mt-1 text-sm text-black/70">{{ $typeLabel($code) }} · {{ $subjectSummary($code) }}</p>
                        <p class="mt-1 text-xs text-black/60">Emitido {{ $code->issued_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    @if ($code->isRevoked())
                        <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Revocado</span>
                    @else
                        <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('verification.show', $code->code) }}" target="_blank" rel="noopener"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg border border-black/10 px-3 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                        Ver
                    </a>
                    <a href="{{ route('verification.print', $code->code) }}" target="_blank" rel="noopener"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg border border-black/10 px-3 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                        Imprimir
                    </a>
                    @if (! $code->isRevoked())
                        @can('revoke', $code)
                            <button type="button" wire:click="revoke('{{ $code->id }}')"
                                wire:confirm="¿Revocar este comprobante? Esta acción no se puede deshacer."
                                class="col-span-2 inline-flex min-h-10 items-center justify-center rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                Revocar
                            </button>
                        @endcan
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-black/15 bg-white px-4 py-10 text-center">
                <p class="text-sm font-medium text-ink">No hay comprobantes que coincidan con los filtros.</p>
                <p class="mt-1 text-sm text-black/60">Ajusta la búsqueda o el estado seleccionado.</p>
            </div>
        @endforelse
    </section>

    @if ($codes->hasPages())
        <section class="rounded-2xl border border-black/10 bg-white p-3 shadow-sm">
            {{ $codes->links('components.pagination') }}
        </section>
    @endif
</div>
