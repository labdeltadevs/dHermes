@props([
    'slides' => [],
    'sessionKey' => '',
])

@if (count($slides) > 0)
    <div data-hero-slides x-data="{
            open: false,
            index: 0,
            storageKey: 'dhermes.hero-slides.{{ $sessionKey }}',
            slides: @js(array_values($slides)),
            init() {
                this.open = sessionStorage.getItem(this.storageKey) !== '1';
            },
            next() {
                this.index = (this.index + 1) % this.slides.length;
            },
            previous() {
                this.index = (this.index - 1 + this.slides.length) % this.slides.length;
            },
            close() {
                this.open = false;
                sessionStorage.setItem(this.storageKey, '1');
            },
        }" x-cloak x-on:keydown.escape.window="open && close()">
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4 backdrop-blur-sm"
            x-on:click.self="close()">
            <div x-show="open" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-95" role="dialog" aria-modal="true"
                aria-labelledby="hero-slide-title"
                class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-line bg-surface shadow-pop">
                <div class="relative isolate overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-ink px-6 pb-8 pt-6 text-white sm:px-8">
                    <div class="pointer-events-none absolute -right-10 -top-12 size-40 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="pointer-events-none absolute -bottom-16 -left-8 size-40 rounded-full bg-mint-400/20 blur-2xl"></div>

                    <div class="relative flex items-start justify-between gap-4">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/90">
                            <span class="size-1.5 rounded-full bg-mint-300"></span>
                            Comunicado
                        </span>

                        <button type="button" x-on:click="close()" aria-label="Cerrar comunicado"
                            class="-mr-2 -mt-1 rounded-full p-2 text-white/80 transition hover:bg-white/15 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <h2 id="hero-slide-title" x-text="slides[index].title"
                        class="relative mt-5 text-xl font-semibold leading-tight tracking-[-0.01em] sm:text-2xl"></h2>
                </div>

                <div class="px-6 py-6 sm:px-8">
                    <p x-text="slides[index].body" class="whitespace-pre-line text-sm leading-6 text-ink-soft"></p>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2" aria-hidden="true">
                            <template x-if="slides.length > 1">
                                <div class="flex items-center gap-2">
                                    <button type="button" x-on:click="previous()" aria-label="Comunicado anterior"
                                        class="flex size-9 items-center justify-center rounded-full border border-line text-muted transition hover:border-brand-300 hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="m15 18-6-6 6-6" />
                                        </svg>
                                    </button>

                                    <div class="flex items-center gap-1.5 px-1">
                                        <template x-for="(slide, slideIndex) in slides" :key="slideIndex">
                                            <button type="button" x-on:click="index = slideIndex"
                                                :aria-label="'Ir al comunicado ' + (slideIndex + 1)"
                                                :class="index === slideIndex ? 'w-6 bg-brand-600' : 'w-2 bg-line'"
                                                class="h-2 rounded-full transition-all"></button>
                                        </template>
                                    </div>

                                    <button type="button" x-on:click="next()" aria-label="Comunicado siguiente"
                                        class="flex size-9 items-center justify-center rounded-full border border-line text-muted transition hover:border-brand-300 hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="m9 18 6-6-6-6" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <button type="button" x-on:click="close()" class="btn btn-primary w-full sm:w-auto">
                            Entendido
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
