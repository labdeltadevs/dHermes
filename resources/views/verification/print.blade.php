<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ __('Comprobante') }} · {{ config('app.name', 'dHermes') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @page {
                size: A4;
                margin: 16mm;
            }
        </style>
    </head>
    <body class="min-h-dvh bg-canvas text-ink antialiased print:bg-white">
        @php
            $money = fn (string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
            $subjectLabel = $subject instanceof \App\Contracts\Verifiable ? $subject->verificationLabel() : 'Comprobante';
            $isCollection = $subject instanceof \App\Models\CollectionEvent;
            $isCashSession = $subject instanceof \App\Models\CashSession;
            $statusLabel = match ($result) {
                \App\Support\Verification\VerificationResult::Valid => 'Válido',
                \App\Support\Verification\VerificationResult::Tampered => 'Alterado',
                \App\Support\Verification\VerificationResult::Revoked => 'Revocado',
                default => 'Desconocido',
            };
        @endphp

        <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 sm:py-10 print:max-w-none print:px-0 print:py-0">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
                <a href="{{ route('verification.show', $code) }}" class="text-sm font-semibold text-brand-700 hover:text-brand-800">
                    Verificación pública
                </a>
                <button type="button" onclick="window.print()"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-ink px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black/80">
                    Imprimir
                </button>
            </div>

            <article class="rounded-3xl border border-black/10 bg-white p-6 shadow-xl shadow-black/[0.04] sm:p-8 print:rounded-none print:border-0 print:p-0 print:shadow-none">
                <header class="flex flex-col gap-4 border-b border-black/10 pb-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">{{ config('app.name', 'dHermes') }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink">{{ $tenant?->name ?? 'Comprobante' }}</h1>
                        <p class="mt-1.5 text-sm text-black/60">Comprobante de {{ strtolower($subjectLabel) }}</p>
                    </div>

                    <span class="inline-flex w-fit shrink-0 rounded-full border px-3 py-1 text-xs font-semibold
                        @class([
                            'border-emerald-200 bg-emerald-50 text-emerald-800' => $result === \App\Support\Verification\VerificationResult::Valid,
                            'border-amber-200 bg-amber-50 text-amber-800' => $result === \App\Support\Verification\VerificationResult::Revoked,
                            'border-rose-200 bg-rose-50 text-rose-800' => $result === \App\Support\Verification\VerificationResult::Tampered,
                            'border-black/10 bg-canvas text-ink' => $result === \App\Support\Verification\VerificationResult::NotFound,
                        ])">
                        {{ $statusLabel }}
                    </span>
                </header>

                <section class="mt-6 grid gap-6 sm:grid-cols-[1fr_auto]">
                    <div class="min-w-0 space-y-5">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                <dt class="font-medium text-black/60">Código</dt>
                                <dd class="mt-1 break-all font-mono text-xs font-semibold text-ink">{{ $code }}</dd>
                            </div>

                            <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                <dt class="font-medium text-black/60">Tipo</dt>
                                <dd class="mt-1 font-semibold text-ink">{{ $subjectLabel }}</dd>
                            </div>

                            @if ($isCollection)
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Monto</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->currency }} {{ $money($subject->amount) }}</dd>
                                </div>
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Fecha</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->collected_on?->format('d/m/Y') ?? '—' }}</dd>
                                </div>
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Factura</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->invoice?->number ?? '—' }}</dd>
                                </div>
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Cliente</dt>
                                    <dd class="mt-1 truncate font-semibold text-ink">{{ $subject->invoice?->customer?->name ?? '—' }}</dd>
                                </div>
                            @elseif ($isCashSession)
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Esperado</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->currency }} {{ $money($subject->expected_amount) }}</dd>
                                </div>
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Contado</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->counted_amount !== null ? $subject->currency.' '.$money($subject->counted_amount) : '—' }}</dd>
                                </div>
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Diferencia</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->difference !== null ? $subject->currency.' '.$money($subject->difference) : '—' }}</dd>
                                </div>
                                <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                    <dt class="font-medium text-black/60">Apertura</dt>
                                    <dd class="mt-1 font-semibold text-ink">{{ $subject->opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="rounded-xl border border-black/5 bg-canvas px-4 py-3 text-sm">
                            <p class="font-medium text-black/60">Verificación pública</p>
                            <p class="mt-1 break-all font-mono text-xs font-semibold text-ink">{{ $publicUrl }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col items-center justify-start gap-2">
                        <div class="rounded-2xl border border-black/10 bg-white p-3">
                            {!! $qrSvg !!}
                        </div>
                        <p class="max-w-44 text-center text-xs leading-5 text-black/60">Escanee para verificar el comprobante.</p>
                    </div>
                </section>

                <footer class="mt-8 border-t border-black/10 pt-4 text-xs leading-5 text-black/60">
                    <p>Este comprobante fue emitido electrónicamente por {{ $tenant?->name ?? config('app.name', 'dHermes') }}.</p>
                    <p class="mt-1">La autenticidad puede comprobarse en la dirección indicada. No comparta este documento si fue revocado.</p>
                </footer>
            </article>
        </main>
    </body>
</html>
