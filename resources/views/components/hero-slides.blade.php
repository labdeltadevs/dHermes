@props([
    'slides' => [],
    'sessionKey' => '',
])

@php
    // Normaliza los slides para asegurar claves opcionales.
    // Claves nuevas (todas opcionales): 'badge' (etiqueta, ej. "Novedad", "Mantenimiento") y 'date'.
    $normalizedSlides = collect($slides)
        ->map(
            fn($s) => array_merge(
                [
                    'title' => '',
                    'body' => '',
                    'badge' => 'Comunicado',
                    'date' => null,
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
        more: false,
        scrolled: false,

        get current() { return this.slides[this.index] || {}; },
        get isLast() { return this.index === this.slides.length - 1; },

        init() {
            // Pre-renderiza el cuerpo (formato ligero y seguro, ver format())
            this.slides = this.slides.map((s) => ({ ...s, html: this.format(s.body) }));
            this.open = sessionStorage.getItem(this.storageKey) !== '1';

            this.$watch('open', (value) => {
                document.body.style.overflow = value ? 'hidden' : '';
                if (value) this.$nextTick(() => { this.$refs.dialog?.focus();
                    this.checkScroll(); });
            });
            this.$watch('index', () => this.$nextTick(() => {
                if (this.$refs.scroller) this.$refs.scroller.scrollTop = 0;
                this.checkScroll();
            }));
            if (this.open) this.$nextTick(() => this.checkScroll());
        },
        destroy() { document.body.style.overflow = ''; },

        next() { if (!this.isLast) this.index++; },
        previous() { if (this.index > 0) this.index--; },
        goTo(i) { this.index = i; },
        close() {
            this.open = false;
            try { sessionStorage.setItem(this.storageKey, '1'); } catch (e) {}
        },

        // Sombras de scroll: indican que hay más contenido arriba/abajo
        checkScroll() {
            const el = this.$refs.scroller;
            if (!el) return;
            this.scrolled = el.scrollTop > 4;
            this.more = el.scrollHeight - el.scrollTop - el.clientHeight > 8;
        },

        onKey(e) {
            if (!this.open) return;
            if (e.key === 'Escape') this.close();
            else if (e.key === 'ArrowRight') this.next();
            else if (e.key === 'ArrowLeft') this.previous();
            else if (e.key === 'Tab') this.trap(e);
        },
        // Mantiene el foco dentro del diálogo
        trap(e) {
            const f = [...this.$refs.dialog.querySelectorAll('a[href], button:not([disabled])')]
                .filter((el) => el.offsetParent !== null);
            if (!f.length) return;
            const first = f[0],
                last = f[f.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.dialog)) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },

        onTouchStart(e) { this.touchStartX = e.changedTouches[0].clientX; },
        onTouchEnd(e) {
            const diff = this.touchStartX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 60) diff > 0 ? this.next() : this.previous();
        },

        // Formato ligero y SEGURO (el texto se escapa antes de generar HTML):
        //   ## Título o LÍNEA EN MAYÚSCULAS → subtítulo
        //   - ítem / * ítem → lista (las líneas siguientes sin viñeta continúan el ítem)
        //   **negrita**, `código`
        //   línea en blanco → separa bloques
        format(text) {
            const esc = (s) => { const d = document.createElement('div');
                d.textContent = s; return d.innerHTML; };
            const inline = (s) => esc(s)
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/`(.+?)`/g, '<code>$1</code>');
            const blocks = [];
            let cur = null;
            String(text || '').split(/\r?\n/).forEach((raw) => {
                const line = raw.trim();
                if (!line) { cur = null; return; }

                // Subtítulo: '## Texto' o una línea completa en MAYÚSCULAS
                const isCaps = line.length <= 60 && /[A-ZÁÉÍÓÚÑ]{3}/.test(line) && !/[a-záéíóúñü]/.test(line);
                if (line.startsWith('## ') || isCaps) {
                    blocks.push({ t: 'h', v: line.replace(/^##\s+/, '') });
                    cur = null;
                    return;
                }

                // Viñeta
                const li = line.match(/^[-*•]\s+(.*)$/);
                if (li) {
                    if (!cur || cur.t !== 'ul') { cur = { t: 'ul', v: [] };
                        blocks.push(cur); }
                    cur.v.push(li[1]);
                    return;
                }

                // Texto plano: continúa la viñeta anterior o el párrafo actual
                if (cur && cur.t === 'ul') cur.v[cur.v.length - 1] += ' ' + line;
                else if (cur && cur.t === 'p') cur.v.push(line);
                else { cur = { t: 'p', v: [line] };
                    blocks.push(cur); }
            });

            return blocks.map((b) => {
                if (b.t === 'h') return '<h3>' + inline(b.v) + '</h3>';
                if (b.t === 'ul') return '<ul>' + b.v.map((i) => '<li>' + inline(i) + '</li>').join('') + '</ul>';
                return '<p>' + b.v.map(inline).join('<br>') + '</p>';
            }).join('');
        }
    }" x-on:keydown.window="onKey($event)" x-on:resize.window.debounce.150ms="checkScroll()"
        x-cloak data-hero-slides>

        {{-- Backdrop --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-ink/50 backdrop-blur-[3px]" x-on:click="close()" aria-hidden="true"></div>

        {{-- Wrapper: en móvil funciona como "bottom sheet", en escritorio centrado --}}
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="pointer-events-none fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6">

            <div x-ref="dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="hero-slide-title"
                aria-describedby="hero-slide-body" x-show="open"
                x-transition:enter="transition ease-out duration-300 delay-75"
                x-transition:enter-start="opacity-0 translate-y-8 sm:scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-8"
                x-on:touchstart.passive="onTouchStart($event)" x-on:touchend.passive="onTouchEnd($event)"
                class="pointer-events-auto relative flex max-h-[92dvh] w-full max-w-full flex-col overflow-hidden rounded-t-[28px] bg-surface shadow-[0_24px_64px_-20px_rgba(17,24,39,0.45)] outline-none ring-1 ring-ink/5 sm:max-h-[min(90dvh,820px)] sm:max-w-2xl sm:rounded-[28px] lg:max-w-3xl">

                {{-- ============ HEADER (fijo, compacto) ============ --}}
                <header class="relative shrink-0 px-5 pb-5 pt-4 text-white sm:px-8 sm:pb-6 sm:pt-5"
                    :class="!current.gradient_from ? 'bg-gradient-to-br from-brand-500 via-brand-500 to-ink-soft' : ''"
                    :style="current.gradient_from ?
                        `background: linear-gradient(135deg, ${current.gradient_from} 0%, ${current.gradient_to || '#1f2937'} 100%)` :
                        ''">

                    <div class="relative flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-white backdrop-blur-md">
                                <span class="size-1.5 rounded-full bg-mint-300"></span>
                                <span x-text="current.badge || 'Comunicado'"></span>
                            </span>
                            <span x-show="current.date" x-text="current.date"
                                class="truncate text-[11px] font-medium text-white/70"></span>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <span x-show="slides.length > 1" class="text-[11px] font-medium tabular-nums text-white/70"
                                x-text="`${index + 1} / ${slides.length}`"></span>
                            <button type="button" x-on:click="close()" aria-label="Cerrar comunicado"
                                class="flex size-8 items-center justify-center rounded-full bg-white/15 text-white transition-colors duration-200 hover:bg-white/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25"
                                        d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <h2 id="hero-slide-title" x-text="current.title"
                        class="relative mt-3.5 text-balance text-[22px] font-semibold leading-tight tracking-tight sm:text-[26px]">
                    </h2>

                    {{-- Progreso --}}
                    <div class="absolute inset-x-0 bottom-0 h-[3px] bg-white/20" x-show="slides.length > 1">
                        <div class="h-full rounded-r-full bg-white/85 transition-all duration-500 ease-out"
                            :style="`width: ${((index + 1) / slides.length) * 100}%`"></div>
                    </div>
                </header>

                {{-- ============ CONTENIDO (único bloque con scroll) ============ --}}
                <div class="relative flex min-h-0 flex-1 flex-col">
                    {{-- Sombra superior al hacer scroll --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-10 h-4 bg-gradient-to-b from-ink/10 to-transparent transition-opacity duration-200"
                        :class="scrolled ? 'opacity-100' : 'opacity-0'"></div>

                    <div x-ref="scroller" x-on:scroll.passive="checkScroll()" aria-live="polite"
                        class="min-h-0 flex-1 overflow-y-auto overscroll-contain scroll-smooth">

                        <template x-for="(slide, i) in slides" :key="i">
                            <article x-show="index === i" x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0">

                                {{-- Imagen a todo el ancho (hace scroll con el contenido) --}}
                                <template x-if="slide.image">
                                    <img :src="slide.image" :alt="slide.title" loading="lazy"
                                        class="aspect-[16/7] w-full bg-canvas object-cover">
                                </template>

                                {{-- Cuerpo: tipografía cómoda para lectura larga --}}
                                <div :id="index === i ? 'hero-slide-body' : null" x-html="slide.html"
                                    class="select-text px-5 py-5 text-[14px] leading-relaxed text-ink-soft sm:px-8 sm:py-7 sm:text-[15px]
                                           [&_p]:mt-3 [&_p:first-child]:mt-0
                                           [&_h3]:mb-1 [&_h3]:mt-6 [&_h3]:text-[15px] [&_h3]:font-semibold [&_h3]:text-ink [&_h3:first-child]:mt-0
                                           [&_ul]:mt-3 [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_ul]:list-disc [&_ul]:marker:text-ink/30
                                           [&_strong]:font-semibold [&_strong]:text-ink
                                           [&_code]:rounded [&_code]:bg-canvas [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-[12.5px] [&_code]:text-ink">
                                </div>
                            </article>
                        </template>
                    </div>

                    {{-- Sombra inferior: hay más contenido --}}
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-10 bg-gradient-to-t from-surface via-surface/80 to-transparent transition-opacity duration-200"
                        :class="more ? 'opacity-100' : 'opacity-0'"></div>
                </div>

                {{-- ============ FOOTER (fijo, siempre visible) ============ --}}
                <footer
                    class="shrink-0 border-t border-line bg-surface px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4 sm:px-8 sm:pb-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                        {{-- Navegación: anterior + puntos --}}
                        <div class="order-2 flex items-center justify-center gap-3 sm:order-1 sm:justify-start"
                            x-show="slides.length > 1">
                            <button type="button" x-on:click="previous()" :disabled="index === 0"
                                aria-label="Comunicado anterior"
                                class="flex size-8 items-center justify-center rounded-full border border-line text-ink transition-colors duration-200 hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25"
                                        d="m15 18-6-6 6-6" />
                                </svg>
                            </button>

                            <div class="flex items-center gap-1.5">
                                <template x-for="(s, si) in slides" :key="si">
                                    <button type="button" x-on:click="goTo(si)"
                                        :aria-label="`Ir al comunicado ${si + 1}`"
                                        :aria-current="index === si ? 'true' : 'false'"
                                        :class="index === si ? 'w-6 bg-brand-500' : 'w-1.5 bg-ink/15 hover:bg-ink/30'"
                                        class="h-1.5 rounded-full transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"></button>
                                </template>
                            </div>
                        </div>

                        {{-- Acciones --}}
                        <div
                            class="order-1 flex flex-col-reverse gap-2 sm:order-2 sm:ml-auto sm:flex-row sm:items-center sm:gap-2.5">
                            <template x-if="current.cta_url">
                                <a :href="current.cta_url" x-text="current.cta_label || 'Saber más'"
                                    class="inline-flex items-center justify-center rounded-full border border-line bg-surface px-5 py-2.5 text-[13px] font-medium text-ink transition-colors duration-200 hover:border-ink/15 hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"></a>
                            </template>

                            <button type="button" x-on:click="isLast ? close() : next()"
                                class="inline-flex items-center justify-center gap-1.5 rounded-full bg-ink px-6 py-2.5 text-[13px] font-medium text-white transition-all duration-200 hover:bg-ink-soft hover:shadow-lg hover:shadow-ink/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ink/40 focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
                                <span x-text="isLast ? 'Entendido' : 'Siguiente'"></span>
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25"
                                        d="m9 5 7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>
@endif
