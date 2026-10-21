<?php

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Auditoría')] class extends Component
{
    use WithPagination;

    public string $action = '';

    public string $entity = '';

    public string $actor = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AuditEvent::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'entity', 'actor', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['action', 'entity', 'actor', 'from', 'to']);
        $this->resetPage();
    }

    public function render()
    {
        return $this->view()->with([
            'events' => $this->eventsQuery()
                ->paginate(25)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    /**
     * @return Builder<AuditEvent>
     */
    private function eventsQuery(): Builder
    {
        $query = AuditEvent::query()->with('actor:id,name,email');

        $action = trim($this->action);

        if ($action !== '') {
            $query->whereLike('action', '%'.$action.'%');
        }

        $entity = trim($this->entity);

        if ($entity !== '') {
            $query->whereLike('entity', '%'.$entity.'%');
        }

        $actor = trim($this->actor);

        if ($actor !== '') {
            $query->whereHas('actor', function (Builder $query) use ($actor): void {
                $query->where('id', $actor)
                    ->orWhere('email', 'like', '%'.$actor.'%')
                    ->orWhere('name', 'like', '%'.$actor.'%');
            });
        }

        if ($this->isDate($this->from)) {
            $query->whereDate('occurred_at', '>=', $this->from);
        }

        if ($this->isDate($this->to)) {
            $query->whereDate('occurred_at', '<=', $this->to);
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('id');
    }

    private function isDate(string $value): bool
    {
        return $value !== '' && Carbon::hasFormat($value, 'Y-m-d');
    }
};
?>

@php
$detailValue = function (mixed $value): string {
    if ($value === null) {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? 'Sí' : 'No';
    }

    if (is_array($value)) {
        return collect($value)
            ->map(fn (mixed $item): string => is_scalar($item) || $item === null
                ? (string) $item
                : (string) json_encode($item))
            ->implode(', ');
    }

    return (string) $value;
};
@endphp

<div class="space-y-5 sm:space-y-6">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-700">Administración</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Auditoría</h1>
            <p class="mt-1.5 text-sm text-black/60">Consulta los eventos registrados en tu empresa. Esta vista es de solo lectura.</p>
        </div>

        @if ($action !== '' || $entity !== '' || $actor !== '' || $from !== '' || $to !== '')
            <button type="button" wire:click="clearFilters"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white px-4 py-2.5 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03]">
                Limpiar filtros
            </button>
        @endif
    </section>

    <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label for="audit-action" class="mb-1.5 block text-sm font-medium text-black/70">Acción</label>
                <input id="audit-action" type="search" wire:model.live.debounce.400ms="action"
                    placeholder="customer.collector_assigned"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="audit-entity" class="mb-1.5 block text-sm font-medium text-black/70">Entidad</label>
                <input id="audit-entity" type="search" wire:model.live.debounce.400ms="entity"
                    placeholder="customer"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div>
                <label for="audit-actor" class="mb-1.5 block text-sm font-medium text-black/70">Actor</label>
                <input id="audit-actor" type="search" wire:model.live.debounce.400ms="actor"
                    placeholder="Nombre, correo o ID"
                    class="block min-h-11 w-full rounded-xl border border-black/15 px-3.5 py-2.5 text-base text-ink outline-none transition placeholder:text-black/60 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3 sm:col-span-2 xl:col-span-1">
                <div>
                    <label for="audit-from" class="mb-1.5 block text-sm font-medium text-black/70">Desde</label>
                    <input id="audit-from" type="date" wire:model.live="from"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                </div>

                <div>
                    <label for="audit-to" class="mb-1.5 block text-sm font-medium text-black/70">Hasta</label>
                    <input id="audit-to" type="date" wire:model.live="to"
                        class="block min-h-11 w-full rounded-xl border border-black/15 bg-white px-3.5 py-2.5 text-base text-ink outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 sm:text-sm">
                </div>
            </div>
        </div>
    </section>

    <section class="hidden overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/10">
                <thead class="bg-canvas">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Actor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Acción</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Entidad / ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-black/60">Detalles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    @forelse ($events as $event)
                        <tr wire:key="audit-{{ $event->id }}" class="align-top transition hover:bg-brand-50/40">
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-black/70">
                                <p>{{ $event->occurred_at->format('d/m/Y') }}</p>
                                <p class="mt-0.5 text-xs text-black/60">{{ $event->occurred_at->format('H:i:s') }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm">
                                @if ($event->actor)
                                    <p class="font-medium text-ink">{{ $event->actor->name }}</p>
                                    <p class="mt-0.5 text-xs text-black/60">{{ $event->actor->email }}</p>
                                @else
                                    <p class="font-medium text-black/70">Sistema</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-ink">{{ $event->action }}</td>
                            <td class="px-4 py-4 text-sm text-black/70">
                                <p>{{ $event->entity }}</p>
                                @if ($event->entity_id)
                                    <p class="mt-0.5 break-all text-xs text-black/60">{{ $event->entity_id }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-black/70">
                                @if (empty($event->details))
                                    <span class="text-black/40">—</span>
                                @else
                                    <details class="group">
                                        <summary class="cursor-pointer text-sm font-medium text-brand-700 hover:text-brand-800">Ver detalles</summary>
                                        <dl class="mt-2 space-y-1.5 rounded-xl bg-canvas p-3 text-xs">
                                            @foreach ($event->details as $key => $value)
                                                <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-2">
                                                    <dt class="font-semibold text-black/60">{{ $key }}</dt>
                                                    <dd class="break-words text-ink">{{ $detailValue($value) }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-ink">Sin eventos con estos filtros.</p>
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

    <section class="space-y-3 md:hidden" aria-label="Eventos de auditoría">
        @forelse ($events as $event)
            <article wire:key="audit-card-{{ $event->id }}" class="rounded-2xl border border-black/10 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-ink">{{ $event->action }}</p>
                        <p class="mt-0.5 text-xs text-black/60">{{ $event->occurred_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-black/[0.04] px-2.5 py-1 text-xs font-semibold text-black/60">{{ $event->entity }}</span>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-canvas p-3 text-xs">
                    <div>
                        <dt class="font-medium text-black/60">Actor</dt>
                        <dd class="mt-0.5 truncate font-semibold text-ink">{{ $event->actor?->name ?? 'Sistema' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-black/60">Entidad / ID</dt>
                        <dd class="mt-0.5 break-all font-semibold text-ink">{{ $event->entity }}{{ $event->entity_id ? ' · '.$event->entity_id : '' }}</dd>
                    </div>
                </dl>

                @if (! empty($event->details))
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm font-medium text-brand-700 hover:text-brand-800">Ver detalles</summary>
                        <dl class="mt-2 space-y-1.5 rounded-xl bg-canvas p-3 text-xs">
                            @foreach ($event->details as $key => $value)
                                <div>
                                    <dt class="font-semibold text-black/60">{{ $key }}</dt>
                                    <dd class="mt-0.5 break-words text-ink">{{ $detailValue($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </details>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-black/15 bg-white px-4 py-10 text-center">
                <p class="text-sm font-medium text-ink">Sin eventos con estos filtros.</p>
                <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Limpiar filtros
                </button>
            </div>
        @endforelse
    </section>

    @if ($events->hasPages())
        <section class="rounded-2xl border border-black/10 bg-white p-3 shadow-sm">
            {{ $events->links('components.pagination') }}
        </section>
    @endif
</div>
