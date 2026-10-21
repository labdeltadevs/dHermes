@if ($paginator->hasPages())
    <nav class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between" aria-label="Paginación"
        wire:loading.class="pointer-events-none opacity-60" wire:loading.attr="aria-busy">
        <p class="text-xs text-black/60 sm:text-sm">
            Mostrando {{ $paginator->firstItem() ?? 0 }} a {{ $paginator->lastItem() ?? 0 }} de {{ $paginator->total() }} resultados
        </p>

        <div class="flex flex-wrap items-center justify-center gap-0.5 sm:flex-nowrap sm:justify-end sm:gap-1.5">
            @if ($paginator->onFirstPage())
                <button type="button" disabled aria-label="Página anterior"
                    class="inline-flex size-9 shrink-0 cursor-not-allowed items-center justify-center gap-1.5 rounded-xl border border-black/10 bg-white px-2 text-sm font-semibold text-black/30 sm:size-auto sm:min-h-11 sm:px-4"><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" />
                    </svg>
                    <span class="hidden sm:inline">Anterior</span>
                </button>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                    wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')"
                    class="inline-flex size-9 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-black/15 bg-white px-2 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03] focus:outline-none focus:ring-4 focus:ring-brand-500/15 sm:size-auto sm:min-h-11 sm:px-4"
                    aria-label="Página anterior">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" />
                    </svg>
                    <span class="hidden sm:inline">Anterior</span>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true"
                        class="inline-flex size-9 shrink-0 items-center justify-center text-sm font-medium text-black/40 sm:size-11">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-ink text-sm font-semibold text-white shadow-sm sm:size-11">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                                wire:click.prevent="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:key="pagination-{{ $paginator->getPageName() }}-{{ $page }}"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl border border-black/15 bg-white text-sm font-semibold text-black/70 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-4 focus:ring-brand-500/15 sm:size-11"
                                aria-label="Ir a la página {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                    wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')"
                    class="inline-flex size-9 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-black/15 bg-white px-2 text-sm font-semibold text-black/70 transition hover:bg-black/[0.03] focus:outline-none focus:ring-4 focus:ring-brand-500/15 sm:size-auto sm:min-h-11 sm:px-4"
                    aria-label="Página siguiente">
                    <span class="hidden sm:inline">Siguiente</span>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" />
                    </svg>
                </a>
            @else
                <button type="button" disabled aria-label="Página siguiente"
                    class="inline-flex size-9 shrink-0 cursor-not-allowed items-center justify-center gap-1.5 rounded-xl border border-black/10 bg-white px-2 text-sm font-semibold text-black/30 sm:size-auto sm:min-h-11 sm:px-4"><span class="hidden sm:inline">Siguiente</span>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" />
                    </svg>
                </button>
            @endif
        </div>
    </nav>
@endif
