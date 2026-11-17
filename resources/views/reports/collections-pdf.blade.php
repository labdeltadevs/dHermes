<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte de cobranzas</title>
    <style>
        /* ==========================================================
           CONFIGURACIÓN DE PÁGINA — elegir UNA opción
           Opción A · CARTA (8.5 × 11 in)  →  size: letter;
           Opción B · OFICIO (8.5 × 13 in) →  size: 216mm 330mm;
           ========================================================== */
        @page {
            size: letter;
            /* size: 216mm 330mm;   ← descomentar para OFICIO */
            margin: 14mm 12mm 18mm 12mm;
        }

        /*
           Paleta en escala de grises (valores literales para máxima
           compatibilidad con motores PDF como dompdf):
           #111827 texto principal   #374151 texto fuerte
           #6b7280 secundario        #9ca3af terciario
           #d1d5db bordes            #e5e7eb bordes suaves
           #f3f4f6 fondo sección     #f9fafb fondo alterno
        */

        * {
            box-sizing: border-box;
        }

        html,
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            line-height: 1.4;
            color: #111827;
            margin: 1rem;
            padding: 0;
        }

        /* ---------- Encabezado ---------- */
        .doc-head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .doc-head td {
            border: none;
            padding: 0;
            vertical-align: bottom;
        }

        .company {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            color: #6b7280;
            margin-bottom: 2px;
        }

        h1 {
            font-size: 17px;
            font-weight: bold;
            margin: 0;
            color: #111827;
            letter-spacing: -0.3px;
        }

        .head-right {
            text-align: right;
            font-size: 8px;
            color: #6b7280;
            line-height: 1.5;
            white-space: nowrap;
        }

        .head-right strong {
            color: #111827;
            font-weight: bold;
        }

        .head-rule {
            height: 0;
            border: none;
            border-top: 2px solid #111827;
            margin: 7px 0 0;
        }

        /* ---------- Filtros / metadatos ---------- */
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: #f3f4f6;
            border-radius: 4px;
        }

        .meta td {
            padding: 6px 9px;
            font-size: 8px;
            color: #6b7280;
            border: none;
        }

        .meta strong {
            color: #111827;
            font-weight: bold;
        }

        .meta .dot {
            color: #9ca3af;
            margin: 0 5px;
        }

        /* ---------- Tablas ---------- */
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        .data th,
        .data td {
            border: none;
            border-bottom: 0.5px solid #e5e7eb;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
        }

        .data thead {
            display: table-header-group;
        }

        .data thead th {
            background: #f3f4f6;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
            border-top: 0.5px solid #d1d5db;
            border-bottom: 1px solid #9ca3af;
            padding: 5px 6px;
        }

        .data tbody tr {
            page-break-inside: avoid;
        }

        .data tbody tr.alt td {
            background: #f9fafb;
        }

        .data td.num,
        .data th.num {
            text-align: right;
            white-space: nowrap;
        }

        .data td.amt {
            font-weight: bold;
            color: #111827;
        }

        .data .mono {
            font-family: "DejaVu Sans Mono", Menlo, Consolas, monospace;
            font-size: 7.5px;
            color: #9ca3af;
        }

        .data .name {
            font-weight: bold;
            color: #111827;
        }

        .data .invoices {
            font-size: 8px;
            line-height: 1.35;
            color: #4b5563;
        }

        .data .muted {
            color: #6b7280;
        }

        /* ---------- Badges de estado (se distinguen sin color) ----------
           Pagado    → relleno oscuro, texto blanco
           Pendiente → solo contorno
           Anulado   → gris claro, texto tachado
           Otro      → gris neutro                                     */
        .badge {
            display: inline-block;
            padding: 1px 6px;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f3f4f6;
            border: 0.5px solid #d1d5db;
            border-radius: 8px;
            color: #4b5563;
            white-space: nowrap;
        }

        .badge--paid {
            background: #374151;
            border-color: #374151;
            color: #ffffff;
        }

        .badge--pending {
            background: #ffffff;
            border: 0.5px solid #6b7280;
            color: #374151;
        }

        .badge--cancel {
            background: #e5e7eb;
            border-color: #d1d5db;
            color: #6b7280;
            text-decoration: line-through;
        }

        /* ---------- Totales ---------- */
        .totals {
            margin-top: 10px;
            padding: 8px 10px 9px;
            background: #f3f4f6;
            border: 0.5px solid #d1d5db;
            border-left: 3px solid #111827;
            border-radius: 4px;
            page-break-inside: avoid;
        }

        .totals .label {
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        .chip {
            display: inline-block;
            margin: 3px 5px 0 0;
            padding: 4px 10px;
            background: #ffffff;
            border: 0.5px solid #d1d5db;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .chip .cur {
            color: #6b7280;
            margin-right: 4px;
            font-weight: bold;
            font-size: 7.5px;
            letter-spacing: 0.6px;
        }

        .chip .cnt {
            font-size: 7px;
            font-weight: normal;
            color: #9ca3af;
            margin-left: 4px;
        }

        /* ---------- Secciones ---------- */
        .section-title {
            margin: 14px 0 5px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #111827;
            padding-bottom: 4px;
            border-bottom: 1px solid #111827;
            page-break-after: avoid;
        }

        .cur-row td {
            background: #f3f4f6;
            font-weight: bold;
            color: #111827;
            border-bottom: 0.5px solid #d1d5db;
        }

        .empty {
            border: 1px dashed #d1d5db;
            border-radius: 4px;
            padding: 30px 18px;
            text-align: center;
            color: #9ca3af;
            font-size: 9px;
            background: #f9fafb;
        }

        /* ---------- Pie de página ---------- */
        .footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #9ca3af;
            padding-top: 4px;
            border-top: 0.5px solid #d1d5db;
        }

        .page-number:after {
            content: counter(page) " / " counter(pages);
        }
    </style>
</head>

<body>
    {{-- Mapeo de estados a variantes de estilo (normalizado, sin importar mayúsculas) --}}
    @php
        $badgeClass = function (string $status): string {
            $s = mb_strtolower(trim($status));
            return match (true) {
                str_contains($s, 'pag') && !str_contains($s, 'pend') => 'badge--paid',
                str_contains($s, 'pend') => 'badge--pending',
                str_contains($s, 'anul') || str_contains($s, 'cancel') || str_contains($s, 'rechaz') => 'badge--cancel',
                default => '',
            };
        };
    @endphp

    <div class="footer">
        Reporte de cobranzas · {{ $reportCode }} · Generado {{ $generatedAt->format('d/m/Y H:i') }} · Página
        <span class="page-number"></span>
    </div>

    <table class="doc-head">
        <tr>
            <td>
                <div class="company">{{ $tenantName ?? 'Empresa' }}</div>
                <h1>Reporte de cobranzas</h1>
            </td>
            <td class="head-right">
                <strong>N° reporte: {{ $reportCode }}</strong><br>
                Generado {{ $generatedAt->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>
    <hr class="head-rule">

    <table class="meta" style="margin-top: 8px;">
        <tr>
            <td>
                <span><strong>Periodo:</strong> {{ $filters['from'] }} – {{ $filters['to'] }}</span>
                <span class="dot">·</span><span><strong>Estado:</strong> {{ $statusLabel ?? 'Todos' }}</span>
                <span class="dot">·</span><span><strong>Método(s):</strong>
                    {{ $paymentMethodLabel ?? 'Todos' }}</span>
                @if (!empty($filters['currency']))
                    <span class="dot">·</span><span><strong>Moneda:</strong>
                        {{ implode(', ', $filters['currency']) }}</span>
                @endif
                @if ($sellerName !== null)
                    <span class="dot">·</span><span><strong>Vendedor(es):</strong> {{ $sellerName }}</span>
                @endif
                <span class="dot">·</span><span><strong>Generado por:</strong> {{ $generatedByName ?? '—' }}</span>
            </td>
        </tr>
    </table>

    @if ($rows->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 9%">ID app</th>
                    <th style="width: 19%">Factura(s)</th>
                    <th style="width: 19%">Cliente</th>
                    <th style="width: 14%">Cobrado por</th>
                    <th style="width: 12%">Forma de pago</th>
                    <th style="width: 10%">Fecha / hora</th>
                    <th class="num" style="width: 10%">Monto</th>
                    <th style="width: 7%">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td class="mono">{{ $row['number_code'] !== '' ? $row['number_code'] : '—' }}</td>
                        <td class="invoices">{{ $row['invoice'] !== '' ? $row['invoice'] : '—' }}</td>
                        <td>
                            @if ($row['customer'] !== '')
                                @if ($row['cardcode'] !== '')
                                    <span class="mono">({{ $row['cardcode'] }})</span>
                                @endif
                                <span class="name">{{ $row['customer'] }}</span>
                            @else
                                <span class="mono">{{ $row['cardcode'] !== '' ? $row['cardcode'] : '—' }}</span>
                            @endif
                        </td>
                        <td class="muted">{{ $row['seller'] !== '' ? $row['seller'] : '—' }}</td>
                        <td class="muted">{{ $row['method_label'] }}</td>
                        <td class="muted">{{ $row['created_at'] }}</td>
                        <td class="num amt">{{ number_format($row['amount'], 2, ',', '.') }}</td>
                        <td><span class="badge {{ $badgeClass($row['status']) }}">{{ $row['status'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="label">Total general de pagos recibidos</div>
            <div>
                @foreach ($arqueo as $ct)
                    <span class="chip">
                        <span
                            class="cur">{{ $ct['currency'] }}</span>{{ number_format($ct['total'], 2, ',', '.') }}
                        <span class="cnt">{{ number_format($ct['count'], 0, ',', '.') }} cobros</span>
                    </span>
                @endforeach
            </div>
        </div>

        <div class="section-title">Arqueo de cobranzas por método de pago</div>
        <table class="data">
            <thead>
                <tr>
                    <th>Método de pago</th>
                    <th class="num" style="width: 22%">Monto</th>
                    <th class="num" style="width: 12%">Cobros</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($arqueo as $ct)
                    <tr class="cur-row">
                        <td>{{ $ct['currency'] }} — subtotal</td>
                        <td class="num">{{ number_format($ct['total'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($ct['count'], 0, ',', '.') }}</td>
                    </tr>
                    @foreach ($ct['methods'] as $m)
                        <tr>
                            <td class="muted">{{ $m['label'] }}</td>
                            <td class="num">{{ number_format($m['total'], 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($m['count'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Sin cobros para este periodo y filtros.</div>
    @endif
</body>

</html>
