<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ __('Verificación de cobranza') }} · {{ config('app.name', 'dHermes') }}</title>
        <x-pwa-meta />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh bg-canvas text-ink antialiased">
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,rgba(255,103,31,0.12),transparent_36%)]"></div>

        <main class="relative mx-auto flex min-h-dvh max-w-xl flex-col justify-center px-4 py-8 sm:px-6">
            <div class="mb-6 flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-ink text-xs font-extrabold text-brand-500">dH</span>
                    <span>dHermes</span>
                </a>
                <span class="rounded-full border border-black/10 bg-white px-3 py-1 text-xs font-semibold text-black/60">Verificación pública</span>
            </div>

            <div class="rounded-3xl border border-black/10 bg-white p-6 shadow-xl shadow-black/[0.04] sm:p-8">
                <header>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">{{ config('app.name', 'dHermes') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Verificación de comprobante') }}</h1>
                    <p class="mt-1.5 text-sm text-black/60">Consulta el estado del comprobante emitido por la empresa.</p>
                </header>

                @php
                    $tone = match ($result) {
                        \App\Support\Verification\VerificationResult::Valid => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                        \App\Support\Verification\VerificationResult::Tampered => 'border-rose-200 bg-rose-50 text-rose-900',
                        \App\Support\Verification\VerificationResult::Revoked => 'border-amber-200 bg-amber-50 text-amber-900',
                        default => 'border-black/10 bg-canvas text-ink',
                    };
                @endphp

                <section class="mt-6 rounded-2xl border p-5 {{ $tone }}">
                    <p class="text-lg font-semibold">
                        @switch($result)
                            @case(\App\Support\Verification\VerificationResult::Valid)
                                {{ __('Comprobante válido') }}
                                @break
                            @case(\App\Support\Verification\VerificationResult::Tampered)
                                {{ __('Comprobante alterado o inválido') }}
                                @break
                            @case(\App\Support\Verification\VerificationResult::Revoked)
                                {{ __('Comprobante revocado') }}
                                @break
                        @endswitch
                    </p>
                    <p class="mt-2 text-sm leading-6">
                        @switch($result)
                            @case(\App\Support\Verification\VerificationResult::Valid)
                                {{ __('Los datos coinciden con la firma registrada.') }}
                                @break
                            @case(\App\Support\Verification\VerificationResult::Tampered)
                                {{ __('La firma no coincide con los datos. No utilice este comprobante.') }}
                                @break
                            @case(\App\Support\Verification\VerificationResult::Revoked)
                                {{ __('Este código fue anulado por la empresa.') }}
                                @break
                        @endswitch
                    </p>
                </section>

                @php
                    $verifiedCode = $result === \App\Support\Verification\VerificationResult::Valid
                        ? app(\App\Support\Verification\VerifyCode::class)->find($code)
                        : null;
                    $subject = $verifiedCode?->verifiable;
                    $money = fn (string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
                @endphp

                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4 rounded-xl border border-black/5 bg-canvas px-4 py-3">
                        <dt class="font-medium text-black/60">{{ __('Código') }}</dt>
                        <dd class="break-all text-right font-mono text-xs font-semibold sm:text-sm">{{ $code }}</dd>
                    </div>

                    @if ($subject instanceof \App\Contracts\Verifiable)
                        <div class="flex items-start justify-between gap-4 rounded-xl border border-black/5 bg-canvas px-4 py-3">
                            <dt class="font-medium text-black/60">{{ __('Tipo') }}</dt>
                            <dd class="text-right font-semibold text-ink">{{ $subject->verificationLabel() }}</dd>
                        </div>

                        @if ($subject instanceof \App\Models\CollectionEvent)
                            <div class="flex items-start justify-between gap-4 rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                <dt class="font-medium text-black/60">{{ __('Monto') }}</dt>
                                <dd class="text-right font-semibold text-ink">{{ $subject->currency }} {{ $money($subject->amount) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                <dt class="font-medium text-black/60">{{ __('Fecha') }}</dt>
                                <dd class="text-right font-semibold text-ink">{{ $subject->collected_on?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                        @elseif ($subject instanceof \App\Models\CashSession)
                            <div class="flex items-start justify-between gap-4 rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                <dt class="font-medium text-black/60">{{ __('Monto esperado') }}</dt>
                                <dd class="text-right font-semibold text-ink">{{ $subject->currency }} {{ $money($subject->expected_amount) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 rounded-xl border border-black/5 bg-canvas px-4 py-3">
                                <dt class="font-medium text-black/60">{{ __('Fecha') }}</dt>
                                <dd class="text-right font-semibold text-ink">{{ $subject->opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>

                <a href="{{ route('home') }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-black/10 px-4 py-2.5 text-sm font-semibold text-ink transition hover:bg-black/[0.03]">
                    Ir a dHermes
                </a>
            </div>

            <p class="mt-5 text-center text-xs leading-5 text-black/60">
                La verificación no requiere iniciar sesión. Si el comprobante no coincide con tu operación, contacta a la empresa.
            </p>
        </main>
    </body>
</html>
