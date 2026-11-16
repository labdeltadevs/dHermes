<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte de cobranzas</title>
    <style>
        @page {
            margin: 18px 16px 32px 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 9px;
            line-height: 1.35;
            color: #1f2937;
            margin: 0;
        }

        /* ---------- Encabezado ---------- */
        .doc-head {
            border-top: 3px solid #2563eb;
            border-bottom: 2px solid #111827;
            padding: 5px 0 6px;
            margin-bottom: 8px;
        }

        .doc-head table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-head td {
            border: none;
            padding: 0;
            vertical-align: bottom;
        }

        .company {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #2563eb;
            margin-bottom: 2px;
        }

        h1 {
            font-size: 15px;
            margin: 0;
            color: #111827;
        }

        .head-right {
            text-align: right;
            font-size: 9px;
            color: #6b7280;
            line-height: 1.5;
            white-space: nowrap;
        }

        .head-right strong {
            color: #111827;
        }

        .meta {
            margin-top: 5px;
            font-size: 9px;
            color: #4b5563;
        }

        .meta strong {
            color: #111827;
        }

        .meta .dot {
            color: #9ca3af;
            margin: 0 3px;
        }

        /* ---------- Tablas ---------- */
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .data th,
        .data td {
            border: 1px solid #e5e7eb;
            padding: 2.5px 4px;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
        }

        .data thead {
            display: table-header-group;
            /* repite el encabezado en cada página */
        }

        .data thead th {
            background: #f3f4f6;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #374151;
            border-bottom: 1.5px solid #9ca3af;
        }

        .data tr {
            page-break-inside: avoid;
        }

        .data tr.alt td {
            background: #f8fafc;
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
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 9px;
            color: #6b7280;
        }

        .data .name {
            font-weight: bold;
            color: #111827;
        }

        .data .invoices {
            font-size: 9px;
            line-height: 1.4;
            color: #374151;
        }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            color: #334155;
            white-space: nowrap;
        }

        /* ---------- Totales ---------- */
        .totals {
            margin-top: 8px;
            border: 1.5px solid #111827;
            padding: 5px 8px 6px;
            page-break-inside: avoid;
        }

        .totals .label {
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
        }

        .chip {
            display: inline-block;
            margin: 3px 6px 0 0;
            padding: 3px 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }

        .chip .cur {
            color: #2563eb;
            margin-right: 3px;
        }

        .chip .cnt {
            font-size: 7px;
            font-weight: normal;
            color: #6b7280;
            margin-left: 4px;
        }

        /* ---------- Secciones ---------- */
        .section-title {
            margin: 9px 0 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #111827;
            border-left: 3px solid #2563eb;
            padding: 2px 0 2px 7px;
        }

        .cur-row td {
            background: #eff6ff;
            border-color: #93c5fd;
            font-weight: bold;
            color: #1e3a8a;
        }

        .empty {
            border: 1px dashed #cbd5e1;
            padding: 18px;
            text-align: center;
            color: #6b7280;
        }

        .footer {
            position: fixed;
            bottom: -24px;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #9ca3af;
        }

        .page-number:after {
            content: counter(page) " / " counter(pages);
        }
    </style>
</head>

<body>
    <div class="footer">
        Reporte de cobranzas · {{ $reportCode }} · Generado {{ $generatedAt->format('d/m/Y H:i') }} · Página
        <span class="page-number"></span>
    </div>

    <div class="doc-head">
        <table>
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

        <div class="meta">
            <span><strong>Periodo:</strong> {{ $filters['from'] }} – {{ $filters['to'] }}</span>
            <span class="dot">·</span><span><strong>Estado:</strong> {{ $statusLabel ?? 'Todos' }}</span>
            <span class="dot">·</span><span><strong>Método:</strong> {{ $paymentMethodLabel ?? 'Todos' }}</span>
            @if ($filters['currency'] !== '')
                <span class="dot">·</span><span><strong>Moneda:</strong> {{ $filters['currency'] }}</span>
            @endif
            @if ($sellerName !== null)
                <span class="dot">·</span><span><strong>Vendedor:</strong> {{ $sellerName }}</span>
            @endif
            <span class="dot">·</span><span><strong>Generado por:</strong> {{ $generatedByName ?? '—' }}</span>
        </div>
    </div>

    @if ($rows->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 11%">ID app</th>
                    <th style="width: 23%">Factura(s)</th>
                    <th style="width: 23%">Cliente</th>
                    <th style="width: 13%">Forma de pago</th>
                    <th style="width: 11%">Fecha / hora</th>
                    <th class="num" style="width: 11%">Monto</th>
                    <th style="width: 8%">Estado</th>
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
                        <td>{{ $row['method_label'] }}</td>
                        <td>{{ $row['created_at'] }}</td>
                        <td class="num amt">{{ number_format($row['amount'], 2, ',', '.') }}
                        </td>
                        <td><span>{{ $row['status'] }}</span></td>
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
                            <td>{{ $m['label'] }}</td>
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
