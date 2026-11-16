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
    }" x-cloak>

        <!-- Backdrop -->
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-ink/25 backdrop-blur-sm"
            x-on:click="close()"></div>

        <!-- Modal wrapper -->
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">

            <div x-ref="dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="hero-slide-title"
                x-show="open" x-transition:enter="transition ease-out duration-300 delay-75"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95" x-on:click.self="close()"
                x-on:touchstart.passive="onTouchStart($event)" x-on:touchend.passive="onTouchEnd($event)"
                class="pointer-events-auto relative w-full max-w-xl overflow-hidden rounded-3xl bg-surface shadow-2xl outline-none">

                <!-- Progress bar -->
                <div class="absolute inset-x-0 top-0 z-30 h-1 bg-black/10">
                    <div class="h-full bg-white/90 transition-all duration-500 ease-out"
                        :style="`width: ${((index + 1) / slides.length) * 100}%`"></div>
                </div>

                <!-- Close button -->
                <button type="button" x-on:click="close()" aria-label="Cerrar comunicado"
                    class="absolute right-3 top-3 z-30 flex size-9 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur-sm transition hover:bg-black/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Slides track (horizontal carousel) -->
                <div class="overflow-hidden">
                    <div class="flex transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                        :style="`transform: translateX(-${index * 100}%)`">

                        <template x-for="(slide, i) in slides" :key="i">
                            <div class="w-full shrink-0 select-none">

                                <!-- Header -->
                                <div class="relative overflow-hidden px-6 pb-8 pt-12 text-white sm:px-10 sm:pt-14"
                                    :class="!slide.gradient_from ? 'bg-gradient-to-br from-brand-600 via-brand-700 to-ink' : ''"
                                    :style="slide.gradient_from ?
                                        `background: linear-gradient(135deg, ${slide.gradient_from} 0%, ${slide.gradient_to || '#111827'} 100%)` :
                                        ''">

                                    <!-- Decorative blobs -->
                                    <div
                                        class="pointer-events-none absolute -right-12 -top-16 size-48 rounded-full bg-white/10 blur-3xl">
                                    </div>
                                    <div
                                        class="pointer-events-none absolute -bottom-20 -left-10 size-48 rounded-full bg-mint-400/20 blur-3xl">
                                    </div>

                                    <!-- Optional image -->
                                    <template x-if="slide.image">
                                        <img :src="slide.image" :alt="slide.title"
                                            class="relative mb-5 h-32 w-full rounded-2xl border border-white/20 object-cover shadow-xl sm:h-44">
                                    </template>

                                    <div class="relative">
                                        <div class="mb-3 flex items-center gap-2.5">
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-white/90 backdrop-blur-sm">
                                                <span class="size-1.5 animate-pulse rounded-full bg-mint-300"></span>
                                                Comunicado
                                            </span>
                                            <span class="text-[11px] font-semibold text-white/60"
                                                x-text="`${i + 1} / ${slides.length}`"></span>
                                        </div>
                                        <h2 id="hero-slide-title" x-text="slide.title"
                                            class="text-2xl font-bold leading-tight tracking-tight sm:text-[28px]"></h2>
                                    </div>
                                </div>

                                <!-- Body -->
                                <div class="px-6 py-6 sm:px-10 sm:py-8">
                                    <p x-text="slide.body"
                                        class="whitespace-pre-line text-sm leading-relaxed text-ink-soft sm:text-[15px]">
                                    </p>

                                    <!-- Footer actions -->
                                    <div
                                        class="mt-7 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                                        <!-- Dots -->
                                        <div class="order-2 flex items-center justify-center gap-2 sm:order-1 sm:justify-start"
                                            x-show="slides.length > 1">
                                            <template x-for="(s, si) in slides" :key="si">
                                                <button type="button" x-on:click="goTo(si)"
                                                    :aria-label="`Ir al comunicado ${si + 1}`"
                                                    :aria-current="index === si ? 'true' : 'false'"
                                                    :class="index === si ? 'w-7 bg-brand-600' : 'w-2.5 bg-line hover:bg-muted'"
                                                    class="h-2.5 rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"></button>
                                            </template>
                                        </div>

                                        <!-- Buttons -->
                                        <div class="order-1 flex flex-col gap-2 sm:order-2 sm:flex-row sm:items-center">
                                            <template x-if="slide.cta_url">
                                                <a :href="slide.cta_url" x-text="slide.cta_label || 'Saber más'"
                                                    class="btn btn-outline w-full justify-center sm:w-auto"></a>
                                            </template>
                                            <button type="button" x-on:click="close()"
                                                class="btn btn-primary w-full sm:w-auto">
                                                Entendido
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Side arrows (desktop only) -->
                <template x-if="slides.length > 1">
                    <div class="pointer-events-none absolute inset-y-0 left-0 right-0 z-20 hidden sm:block">
                        <button type="button" x-on:click="previous()" aria-label="Comunicado anterior"
                            class="pointer-events-auto absolute left-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur-sm transition hover:bg-black/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="m15 18-6-6 6-6" />
                            </svg>
                        </button>
                        <button type="button" x-on:click="next()" aria-label="Comunicado siguiente"
                            class="pointer-events-auto absolute right-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/25 text-white backdrop-blur-sm transition hover:bg-black/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="m9 18 6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endif
