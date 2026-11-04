<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte de cobranzas</title>
    <style>
        @page {
            margin: 26px 26px 40px 26px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #111827;
            margin: 0;
        }

        h1 {
            font-size: 17px;
            margin: 0 0 2px;
        }

        h2 {
            font-size: 11px;
            margin: 0;
            padding: 6px 8px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-bottom: none;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header .company {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #374151;
        }

        .meta {
            margin-top: 6px;
            font-size: 8.5px;
            color: #4b5563;
        }

        .meta span {
            margin-right: 14px;
        }

        .group {
            margin-bottom: 12px;
            page-break-inside: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
        }

        th {
            background: #f9fafb;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #4b5563;
        }

        td.num,
        th.num {
            text-align: right;
            white-space: nowrap;
        }

        .mono {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 7.5px;
        }

        .subtotal td {
            background: #f9fafb;
            font-weight: bold;
        }

        .total-box {
            margin-top: 8px;
            border: 2px solid #111827;
            padding: 8px 10px;
            page-break-inside: avoid;
        }

        .total-box .label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .total-row {
            width: 100%;
            margin-top: 4px;
        }

        .total-row td {
            border: none;
            padding: 2px 0;
        }

        .total-line {
            font-size: 15px;
            font-weight: bold;
            text-align: right;
        }

        .empty {
            padding: 20px;
            text-align: center;
            border: 1px solid #d1d5db;
            color: #6b7280;
        }

        .footer {
            position: fixed;
            bottom: -26px;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #9ca3af;
        }

        .page-number:after {
            content: counter(page) " / " counter(pages);
        }
    </style>
</head>

<body>
    <div class="footer">
        Reporte de cobranzas · Generado {{ $generatedAt->format('d/m/Y H:i') }} · Página <span class="page-number"></span>
    </div>

    <div class="header">
        <div class="company">{{ $tenantName ?? 'Empresa' }}</div>
        <h1>Reporte de cobranzas</h1>
        <div class="meta">
            <span><strong>Periodo:</strong> {{ $filters['from'] }} a {{ $filters['to'] }}</span>
            <span><strong>Estado:</strong> {{ $statusLabel ?? 'Todos' }}</span>
            <span><strong>Método:</strong> {{ $paymentMethodLabel ?? 'Todos' }}</span>
            @if ($filters['currency'] !== '')
                <span><strong>Moneda:</strong> {{ $filters['currency'] }}</span>
            @endif
            @if ($sellerName !== null)
                <span><strong>Vendedor:</strong> {{ $sellerName }}</span>
            @endif
            <span><strong>Cobros:</strong> {{ number_format($totalCount, 0, ',', '.') }}</span>
        </div>
    </div>

    @forelse ($groups as $group)
        <div class="group">
            <h2>{{ $group['method']->label() }} · {{ $group['rows']->count() }} cobros</h2>

            <table>
                <thead>
                    <tr>
                        <th style="width: 13%">Identificador app</th>
                        <th style="width: 14%">Identificador interno</th>
                        <th style="width: 10%">Factura (NumAtCard)</th>
                        <th class="num" style="width: 9%">Monto asignado</th>
                        <th style="width: 5%">Moneda</th>
                        <th style="width: 10%">Fecha/hora creación</th>
                        <th style="width: 8%">Código cliente</th>
                        <th style="width: 17%">Cliente</th>
                        <th style="width: 8%">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td class="mono">{{ $row['number_code'] !== '' ? $row['number_code'] : '—' }}</td>
                            <td class="mono">{{ $row['id'] }}</td>
                            <td>{{ $row['invoice'] !== '' ? $row['invoice'] : '—' }}</td>
                            <td class="num">{{ number_format($row['amount'], 2, ',', '.') }}</td>
                            <td>{{ $row['currency'] }}</td>
                            <td>{{ $row['created_at'] }}</td>
                            <td class="mono">{{ $row['cardcode'] !== '' ? $row['cardcode'] : '—' }}</td>
                            <td>{{ $row['customer'] !== '' ? $row['customer'] : '—' }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @endforeach

                    @foreach ($group['subtotals'] as $subtotal)
                        <tr class="subtotal">
                            <td colspan="3">Subtotal {{ $group['method']->label() }} ({{ $subtotal['currency'] }})</td>
                            <td class="num">{{ number_format($subtotal['total'], 2, ',', '.') }}</td>
                            <td>{{ $subtotal['currency'] }}</td>
                            <td colspan="2">{{ $subtotal['count'] }} cobros</td>
                            <td colspan="2"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="empty">Sin cobros para este periodo y filtros.</div>
    @endforelse

    @if ($totals->isNotEmpty())
        <div class="total-box">
            <div class="label">Total general de pagos recibidos</div>

            <table class="total-row">
                @foreach ($totals as $total)
                    <tr>
                        <td>{{ $total['currency'] }} · {{ $total['count'] }} cobros</td>
                        <td class="total-line">{{ $total['currency'] }} {{ number_format($total['total'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</body>

</html>
