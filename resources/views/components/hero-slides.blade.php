@props([
    'slides' => [],
    'sessionKey' => '',
])

@php
    // Normaliza los slides para asegurar claves opcionales
    $normalizedSlides = collect($slides)
        ->map(
            fn($s) => array_merge(
                [
                    'title' => '',
                    'body' => '',
                    'image' => null,
                    'gradient_from' => null,
                    'gradient_to' => null,
                    'cta_label' => null,
                    'cta_url' => null,
                ],
                $s,
            ),
        )
        ->values()
        ->all();
@endphp

@if (count($normalizedSlides) > 0)
    <div x-data="{
        open: false,
        index: 0,
        storageKey: 'dhermes.hero-slides.{{ $sessionKey }}',
        slides: @js($normalizedSlides),
        touchStartX: 0,
        init() {
            this.open = sessionStorage.getItem(this.storageKey) !== '1';

            window.addEventListener('keydown', (e) => {
                if (!this.open) return;
                if (e.key === 'Escape') this.close();
                else if (e.key === 'ArrowRight') this.next();
                else if (e.key === 'ArrowLeft') this.previous();
            });

            this.$watch('open', (value) => {
                document.body.style.overflow = value ? 'hidden' : '';
                if (value) this.$nextTick(() => this.$refs.dialog?.focus());
            });
        },
        next() { this.index = (this.index + 1) % this.slides.length; },
        previous() { this.index = (this.index - 1 + this.slides.length) % this.slides.length; },
        goTo(i) { this.index = i; },
        close() {
            this.open = false;
            sessionStorage.setItem(this.storageKey, '1');
        },
        onTouchStart(e) { this.touchStartX = e.changedTouches[0].clientX; },
        onTouchEnd(e) {
            const diff = this.touchStartX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 50) diff > 0 ? this.next() : this.previous();
        }
    }" x-cloak data-hero-slides>

        {{-- Backdrop: suave, sin saturar --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-[3px]" x-on:click="close()"></div>

        {{-- Modal wrapper --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">

            <div x-ref="dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="hero-slide-title"
                x-show="open" x-transition:enter="transition ease-out duration-300 delay-75"
                x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-6"
                x-on:click.self="close()" x-on:touchstart.passive="onTouchStart($event)"
                x-on:touchend.passive="onTouchEnd($event)"
                class="pointer-events-auto relative w-full max-w-lg overflow-hidden rounded-[28px] bg-surface shadow-[0_24px_64px_-20px_rgba(17,24,39,0.35)] ring-1 ring-ink/5 outline-none">

                {{-- Progress bar: refinado, fino --}}
                <div class="absolute inset-x-0 top-0 z-30 h-0.5 bg-ink/5">
                    <div class="h-full rounded-r-full bg-brand-500/80 transition-all duration-500 ease-out"
                        :style="`width: ${((index + 1) / slides.length) * 100}%`"></div>
                </div>

                {{-- Close button: sutil, blanco semitransparente --}}
                <button type="button" x-on:click="close()" aria-label="Cerrar comunicado"
                    class="absolute right-4 top-4 z-30 flex size-8 items-center justify-center rounded-full bg-white/15 text-white/90 backdrop-blur-md transition-all duration-200 hover:bg-white/30 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25"
                            d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                {{-- Slides track --}}
                <div class="overflow-hidden">
                    <div class="flex transition-transform duration-[600ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :style="`transform: translateX(-${index * 100}%)`">

                        <template x-for="(slide, i) in slides" :key="i">
                            <div class="w-full shrink-0 select-none">

                                {{-- Header: gradiente suave, decoración mínima --}}
                                <div class="relative overflow-hidden px-7 pb-9 pt-11 text-white sm:px-9 sm:pt-12"
                                    :class="!slide.gradient_from ?
                                        'bg-gradient-to-b from-brand-500 via-brand-500 to-ink-soft' : ''"
                                    :style="slide.gradient_from ?
                                        `background: linear-gradient(135deg, ${slide.gradient_from} 0%, ${slide.gradient_to || '#1f2937'} 100%)` :
                                        ''">

                                    {{-- Decoración sutil: un solo blob suave --}}
                                    <div
                                        class="pointer-events-none absolute -right-16 -top-20 size-52 rounded-full bg-white/10 blur-3xl">
                                    </div>

                                    {{-- Imagen opcional --}}
                                    <template x-if="slide.image">
                                        <img :src="slide.image" :alt="slide.title"
                                            class="relative mb-6 h-28 w-full rounded-2xl border border-white/15 object-cover shadow-lg sm:h-40">
                                    </template>

                                    <div class="relative">
                                        {{-- Badge + contador --}}
                                        <div class="mb-4 flex items-center gap-2.5">
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-white/90 backdrop-blur-md">
                                                <span class="size-1.5 rounded-full bg-mint-300"></span>
                                                Comunicado
                                            </span>
                                            <span class="text-[11px] font-medium text-white/55"
                                                x-text="`${i + 1} / ${slides.length}`"></span>
                                        </div>

                                        {{-- Título --}}
                                        <h2 id="hero-slide-title" x-text="slide.title"
                                            class="text-[26px] font-semibold leading-[1.2] tracking-tight sm:text-[30px]">
                                        </h2>
                                    </div>
                                </div>

                                {{-- Body --}}
                                <div class="px-7 py-6 sm:px-9 sm:py-7">
                                    <p x-text="slide.body"
                                        class="whitespace-pre-line text-[14px] leading-relaxed text-ink-soft sm:text-[15px]">
                                    </p>

                                    {{-- Footer actions --}}
                                    <div
                                        class="mt-7 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

                                        {{-- Dots --}}
                                        <div class="order-2 flex items-center justify-center gap-1.5 sm:order-1 sm:justify-start"
                                            x-show="slides.length > 1">
                                            <template x-for="(s, si) in slides" :key="si">
                                                <button type="button" x-on:click="goTo(si)"
                                                    :aria-label="`Ir al comunicado ${si + 1}`"
                                                    :aria-current="index === si ? 'true' : 'false'"
                                                    :class="index === si ? 'w-6 bg-brand-500' :
                                                        'w-1.5 bg-ink/15 hover:bg-ink/30'"
                                                    class="h-1.5 rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"></button>
                                            </template>
                                        </div>

                                        {{-- Buttons --}}
                                        <div
                                            class="order-1 flex flex-col gap-2 sm:order-2 sm:flex-row sm:items-center sm:gap-2.5">
                                            <template x-if="slide.cta_url">
                                                <a :href="slide.cta_url" x-text="slide.cta_label || 'Saber más'"
                                                    class="inline-flex items-center justify-center gap-1.5 rounded-full border border-line bg-surface px-5 py-2.5 text-[13px] font-medium text-ink transition-all duration-200 hover:bg-canvas hover:border-ink/15 sm:w-auto"></a>
                                            </template>
                                            <button type="button" x-on:click="close()"
                                                class="inline-flex items-center justify-center gap-1.5 rounded-full bg-ink px-5 py-2.5 text-[13px] font-medium text-white transition-all duration-200 hover:bg-ink-soft hover:shadow-lg hover:shadow-ink/20 sm:w-auto">
                                                Entendido
                                                <svg class="size-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2.25" d="m9 5 7 7-7 7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Side arrows (desktop) --}}
                <template x-if="slides.length > 1">
                    <div class="pointer-events-none absolute inset-y-0 left-0 right-0 z-20 hidden items-center sm:flex">
                        <button type="button" x-on:click="previous()" aria-label="Comunicado anterior"
                            class="pointer-events-auto group absolute left-3 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition-all duration-200 hover:bg-white/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                            <svg class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25"
                                    d="m15 18-6-6 6-6" />
                            </svg>
                        </button>
                        <button type="button" x-on:click="next()" aria-label="Comunicado siguiente"
                            class="pointer-events-auto group absolute right-3 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition-all duration-200 hover:bg-white/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70">
                            <svg class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25"
                                    d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
