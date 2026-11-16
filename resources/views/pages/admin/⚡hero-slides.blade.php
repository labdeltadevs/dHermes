<?php

use App\Models\HeroSlide;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Comunicados')] class extends Component {
    public bool $showForm = false;

    public ?string $editingSlideId = null;

    public string $title = '';

    public string $body = '';

    public int $sortOrder = 0;

    public bool $isPublished = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', HeroSlide::class);
    }

    public function create(): void
    {
        Gate::authorize('create', HeroSlide::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $slideId): void
    {
        $slide = HeroSlide::query()->findOrFail($slideId);

        Gate::authorize('update', $slide);

        $this->editingSlideId = $slide->id;
        $this->title = $slide->title;
        $this->body = $slide->body;
        $this->sortOrder = $slide->sort_order;
        $this->isPublished = $slide->is_published;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->title = trim($this->title);
        $this->body = trim($this->body);

        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:999'],
            'isPublished' => ['boolean'],
        ], [
            'title.required' => 'Ingresa un título para el comunicado.',
            'title.max' => 'El título no puede superar los 120 caracteres.',
            'body.required' => 'Ingresa el mensaje del comunicado.',
            'body.max' => 'El mensaje no puede superar los 2000 caracteres.',
        ]);

        if ($this->editingSlideId !== null) {
            $slide = HeroSlide::query()->findOrFail($this->editingSlideId);

            Gate::authorize('update', $slide);

            $slide->update([
                'title' => $this->title,
                'body' => $this->body,
                'sort_order' => $this->sortOrder,
                'is_published' => $this->isPublished,
            ]);

            session()->flash('status', 'Comunicado actualizado.');
        } else {
            Gate::authorize('create', HeroSlide::class);

            HeroSlide::create([
                'title' => $this->title,
                'body' => $this->body,
                'sort_order' => $this->sortOrder,
                'is_published' => $this->isPublished,
                'created_by' => auth()->id(),
            ]);

            session()->flash('status', 'Comunicado creado.');
        }

        $this->resetForm();
    }

    public function togglePublished(string $slideId): void
    {
        $slide = HeroSlide::query()->findOrFail($slideId);

        Gate::authorize('update', $slide);

        $slide->update(['is_published' => ! $slide->is_published]);

        session()->flash('status', $slide->is_published ? 'Comunicado publicado.' : 'Comunicado despublicado.');
    }

    public function delete(string $slideId): void
    {
        $slide = HeroSlide::query()->findOrFail($slideId);

        Gate::authorize('delete', $slide);

        $slide->delete();

        if ($this->editingSlideId === $slideId) {
            $this->resetForm();
        }

        session()->flash('status', 'Comunicado eliminado.');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        return $this->view()->with([
            'slides' => HeroSlide::query()->inDisplayOrder()->get(),
            'stats' => [
                'total' => HeroSlide::query()->count(),
                'published' => HeroSlide::query()->published()->count(),
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->reset('showForm', 'editingSlideId', 'title', 'body', 'sortOrder', 'isPublished');
        $this->resetValidation();
    }
};
?>

<div class="space-y-5 sm:space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 8.25h18M3 12h18M3 15.75h10.5M6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75Z" />
                </svg>
            </div>
            <div>
                <x-breadcrumb :items="[
                    ['label' => 'Inicio', 'url' => route('dashboard')],
                    ['label' => 'Administración'],
                    ['label' => 'Comunicados'],
                ]" />
                <h1 class="page-title">Comunicados</h1>
                <p class="mt-1 text-sm text-muted">
                    Slides que aparecen como popup al inicio del sistema para todos los usuarios.
                </p>
            </div>
        </div>

        <button type="button" wire:click="create" class="btn btn-primary w-full shrink-0 sm:w-auto">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span>Nuevo comunicado</span>
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1 bg-brand-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Total</p>
            <p class="mt-1.5 text-3xl font-bold tabular-nums text-ink">{{ $stats['total'] }}</p>
        </div>

        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-muted">Publicados</p>
            <p class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ $stats['published'] }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($showForm)
        <section class="card p-4 sm:p-6">
            <h2 class="text-base font-semibold text-ink">
                {{ $editingSlideId !== null ? 'Editar comunicado' : 'Nuevo comunicado' }}
            </h2>

            <form wire:submit="save" class="mt-5 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="hero-title" class="label">Título</label>
                        <input id="hero-title" type="text" maxlength="120" wire:model="title"
                            placeholder="Ej.: Mantenimiento programado" required class="input">
                        @error('title')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="hero-body" class="label">Mensaje</label>
                        <textarea id="hero-body" rows="5" maxlength="2000" wire:model="body"
                            placeholder="Escribe el comunicado que verán los usuarios" required class="input"></textarea>
                        <p class="mt-1.5 text-xs text-muted">Se respetan los saltos de línea.</p>
                        @error('body')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="hero-order" class="label">Orden</label>
                        <input id="hero-order" type="number" min="0" max="999" wire:model="sortOrder"
                            class="input">
                        <p class="mt-1.5 text-xs text-muted">Los números menores aparecen primero.</p>
                        @error('sortOrder')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <label for="hero-published" class="flex items-start gap-3 text-sm text-ink">
                            <input id="hero-published" type="checkbox" wire:model="isPublished"
                                class="mt-0.5 h-4 w-4 rounded border-line text-brand-700 focus:ring-brand-500">
                            <span>Publicado (visible para todos los usuarios)</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancel" class="btn btn-outline">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        {{ $editingSlideId !== null ? 'Guardar cambios' : 'Crear comunicado' }}
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section class="hidden overflow-hidden card md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-canvas">
                    <tr>
                        <th class="table-head w-16">Orden</th>
                        <th class="table-head">Comunicado</th>
                        <th class="table-head">Estado</th>
                        <th class="table-head text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($slides as $slide)
                        <tr wire:key="hero-slide-{{ $slide->id }}" class="transition-colors hover:bg-canvas/60">
                            <td class="whitespace-nowrap px-4 py-4 text-sm font-semibold tabular-nums text-ink">
                                {{ $slide->sort_order }}
                            </td>
                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-ink">{{ $slide->title }}</p>
                                <p class="mt-0.5 line-clamp-2 text-sm text-muted">{{ $slide->body }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($slide->is_published)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Publicado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        Borrador
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit('{{ $slide->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-700 transition-colors hover:bg-brand-50">
                                        Editar
                                    </button>
                                    <button type="button" wire:click="togglePublished('{{ $slide->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-gray-100 hover:text-ink">
                                        {{ $slide->is_published ? 'Despublicar' : 'Publicar' }}
                                    </button>
                                    <button type="button" wire:click="delete('{{ $slide->id }}')"
                                        wire:confirm="¿Eliminar este comunicado? Esta acción no se puede deshacer."
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 transition-colors hover:bg-rose-50">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16">
                                <div class="flex flex-col items-center justify-center gap-3 text-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-canvas">
                                        <svg class="h-7 w-7 text-muted" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 8.25h18M3 12h18M3 15.75h10.5M6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75Z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-ink">No hay comunicados</p>
                                        <p class="mt-0.5 text-sm text-muted">Crea el primero para informar a los usuarios.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-3 md:hidden" aria-label="Listado de comunicados">
        @forelse ($slides as $slide)
            <article wire:key="hero-slide-card-{{ $slide->id }}" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">{{ $slide->title }}</p>
                        <p class="mt-1 line-clamp-3 text-sm text-muted">{{ $slide->body }}</p>
                    </div>

                    @if ($slide->is_published)
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Publicado
                        </span>
                    @else
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            Borrador
                        </span>
                    @endif
                </div>

                <p class="mt-3 text-xs font-medium uppercase tracking-wide text-muted">Orden {{ $slide->sort_order }}</p>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="edit('{{ $slide->id }}')" class="btn btn-soft btn-sm">Editar</button>
                    <button type="button" wire:click="togglePublished('{{ $slide->id }}')" class="btn btn-ghost btn-sm">
                        {{ $slide->is_published ? 'Despublicar' : 'Publicar' }}
                    </button>
                </div>

                <button type="button" wire:click="delete('{{ $slide->id }}')"
                    wire:confirm="¿Eliminar este comunicado? Esta acción no se puede deshacer."
                    class="btn btn-outline btn-sm mt-2 w-full text-rose-600">
                    Eliminar
                </button>
            </article>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-line bg-canvas/50 px-6 py-16 text-center">
                <p class="text-sm font-medium text-ink">No hay comunicados</p>
                <p class="text-sm text-muted">Crea el primero para informar a los usuarios.</p>
            </div>
        @endforelse
    </section>
</div>
