<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Comprobante') }} · {{ config('app.name', 'dHermes') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @page {
            size: 80mm auto;
            margin: 0;
        }

        html,
        body {
            width: 80mm;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
        }

        body {
            /* Fuente liviana: usa la fuente interna de la impresora (draft) */
            font-family: "Courier New", Courier, monospace;
            font-weight: 500;
        }

        .receipt {
            width: 80mm;
            /* padding arriba = 0 porque el .prefeed ya hace el colchón */
            padding: 0 4mm 2mm 4mm;
            background: #fff;
            color: #000;
            font-size: 8pt;
            line-height: 1.15;
        }

        /* Espacio en blanco que absorbe la precarga de la impresora */
        .prefeed {
            height: 5mm;
        }

        .center {
            text-align: center;
        }

        .app {
            font-size: 6pt;
            font-weight: 600;
            letter-spacing: 0;
            text-transform: uppercase;
            opacity: .9;
        }

        .tenant {
            font-size: 8.5pt;
            font-weight: 600;
            line-height: 1.1;
            margin-top: 0.5mm;
            word-break: break-word;
        }

        .doctype {
            font-size: 6pt;
            margin-top: 0.5mm;
            text-transform: uppercase;
            opacity: .9;
        }

        .status {
            display: inline-block;
            margin-top: 0.8mm;
            border: 0.5pt dotted #000;
            padding: 0.2mm 1mm;
            font-size: 6pt;
            font-weight: 600;
            letter-spacing: 0;
        }

        .sep {
            border-top: 1px dotted #000;
            margin: 1.5mm 0;
            opacity: .9;
        }

        .line {
            display: flex;
            gap: 1mm;
            align-items: baseline;
            padding: 0.5mm 0;
            font-size: 7pt;
        }

        .line .l {
            font-size: 7pt;
            font-weight: 600;
            white-space: nowrap;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .line .v {
            font-weight: 600;
            font-size: 7pt;
            word-break: break-all;
            flex: 1;
            text-align: right;
        }

        .line .v.left {
            text-align: left;
        }

        .cols {
            display: flex;
            gap: 1.5mm;
        }

        .cols .col {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0.5mm 0;
        }

        .cols .col .l {
            font-size: 7pt;
            font-weight: 600;
            opacity: .9;
            text-transform: uppercase;
        }

        .cols .col .v {
            font-size: 7pt;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.1;
        }

        .mono {
            word-break: break-all;
        }

        .qr-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8mm;
            padding: 0.8mm 0;
        }

        .qr-box {
            width: 20mm;
            height: 20mm;
            line-height: 0;
        }

        .qr-box svg {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        .url {
            font-size: 6pt;
            text-align: center;
            word-break: break-all;
            line-height: 1.1;
            max-width: 72mm;
        }

        .hint {
            font-size: 6pt;
            opacity: .9;
            text-align: center;
        }

        .footer {
            font-size: 6pt;
            line-height: 1.15;
            opacity: .9;
            text-align: center;
            margin-top: 1mm;
        }

        @media screen {
            html {
                width: 100% !important;
            }

            body {
                width: 100% !important;
                background: #e5e5e5;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 12px;
            }

            .receipt {
                box-shadow: 0 1px 8px rgba(0, 0, 0, .18);
                margin-top: 10px;
            }

            .no-print {
                width: 80mm;
                display: flex;
                justify-content: space-between;
                gap: 8px;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    @php
        $money = fn(string|int|float|null $value): string => number_format((float) $value, 2, ',', '.');
        $subjectLabel = $subject instanceof \App\Contracts\Verifiable ? $subject->verificationLabel() : 'Comprobante';
        $isCollection = $subject instanceof \App\Models\CollectionEvent;
        $isCashSession = $subject instanceof \App\Models\CashSession;
        $statusLabel = match ($result) {
            \App\Support\Verification\VerificationResult::Valid => 'VALIDO',
            \App\Support\Verification\VerificationResult::Tampered => 'ALTERADO',
            \App\Support\Verification\VerificationResult::Revoked => 'REVOCADO',
            default => 'DESCONOCIDO',
        };
    @endphp

    <div class="no-print">
        <a href="{{ route('verification.show', $code) }}"
            style="font-size:12px;font-weight:600;color:#000">Verificación</a>
        <button type="button" onclick="window.print()"
            style="background:#000;color:#fff;border:0;border-radius:999px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer">Imprimir</button>
    </div>

    <div class="receipt">
        {{-- COLCHÓN: absorbe la precarga de la impresora --}}
        <div class="prefeed"></div>

        {{-- HEADER --}}
        <div class="center">
            <div class="app">///////////////////////////////////</div>
            <div class="app">{{ config('app.name', 'dHermes') }}</div>
            <div class="tenant">{{ $tenant?->name ?? 'Comprobante' }}</div>
            <div class="doctype">Comprobante de {{ strtolower($subjectLabel) }}</div>
            <div class="status">{{ $statusLabel }}</div>
        </div>

        <div class="sep"></div>

        {{-- DATA --}}
        <div class="line">
            <span class="l">COD:</span>
            <span class="v mono left">{{ $code }}</span>
        </div>

        @if ($isCollection)
            <div class="cols">
                <div class="col"><span class="l">Monto</span><span class="v">{{ $subject->currency }}
                        {{ $money($subject->amount) }}</span></div>
                <div class="col"><span class="l">Fecha</span><span
                        class="v">{{ $subject->collected_on?->format('d/m/Y') ?? '—' }}</span></div>
            </div>
            <div class="cols">
                <div class="col"><span class="l">Factura</span><span
                        class="v">{{ $subject->invoice?->number ?? $subject->flash_invoice_number ?? '—' }}</span>
                </div>
                <div class="col"><span class="l">DocEntry</span><span
                        class="v">{{ $subject->invoice?->docentry ?? ($subject->isFlash() ? 'Cobro flash' : '—') }}</span></div>
            </div>
            <div class="cols">
                <div class="col"><span class="l">Doc. ERP</span><span
                        class="v">{{ $subject->erp_docnum ?? '—' }}</span></div>
                <div class="col"><span class="l">Voucher</span><span
                        class="v">{{ $subject->voucher_code ?? '—' }}</span></div>
            </div>
            <div class="line">
                <span class="l">Cliente:</span>
                <span
                    class="v left">{{ \Illuminate\Support\Str::limit($subject->invoice?->customer?->name ?? $subject->flash_customer_name ?? '—', 32) }}</span>
            </div>
            <div class="line">
                <span class="l">Adj:</span>
                <span class="v left">{{ $subject->proof_path ? 'SI' : 'NO' }} <span class="no-print">
                        @if ($subject->proof_path)
                            -
                            <a href="{{ route('collections.proof', $subject->id) }}" target="_blank">ver</a>
                        @endif
                    </span></span>
            </div>
        @elseif ($isCashSession)
            <div class="cols">
                <div class="col"><span class="l">Esperado</span><span class="v">{{ $subject->currency }}
                        {{ $money($subject->expected_amount) }}</span></div>
                <div class="col"><span class="l">Contado</span><span
                        class="v">{{ $subject->counted_amount !== null ? $subject->currency . ' ' . $money($subject->counted_amount) : '—' }}</span>
                </div>
            </div>
            <div class="cols">
                <div class="col"><span class="l">Diferencia</span><span
                        class="v">{{ $subject->difference !== null ? $subject->currency . ' ' . $money($subject->difference) : '—' }}</span>
                </div>
                <div class="col"><span class="l">Apertura</span><span
                        class="v">{{ $subject->opened_at?->format('d/m H:i') ?? '—' }}</span></div>
            </div>
        @else
            <div class="line"><span class="l">Tipo:</span><span class="v left">{{ $subjectLabel }}</span></div>
        @endif

        <div class="sep"></div>

        {{-- QR --}}
        <div class="qr-wrap">
            <div class="qr-box">{!! $qrSvg !!}</div>
            <div class="url mono">{{ $publicUrl }}</div>
            <div class="hint">Escanee para verificar</div>
        </div>

        <div class="sep"></div>

        <div class="footer">
            Emitido electrónicamente por {{ $tenant?->name ?? config('app.name') }}. Valide en la URL. No comparta si
            es REVOCADO.
        </div>
        <div class="footer">///////////////////////////////////</div>
    </div>
</body>

</html>
