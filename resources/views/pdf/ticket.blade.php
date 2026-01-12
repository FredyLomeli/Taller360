<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket Venta #{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace; /* Fuente tipo ticket */
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        .ticket {
            width: 100%;
            max-width: 300px; /* Ancho típico de impresora térmica */
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 10px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .info {
            font-size: 10px;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        th {
            text-align: left;
            border-bottom: 1px solid #000;
        }
        td {
            padding: 2px 0;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals {
            margin-top: 10px;
            font-weight: bold;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>POS SYSTEM</h1>
            <p>Tepatitlán de Morelos, Jal.</p>
            <p>RFC: XAXX010101000</p>
            <p>Tel: 378-123-4567</p>
        </div>

        <div class="divider"></div>

        <div class="info">
            <p><strong>Folio:</strong> #{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</p>
            <p><strong>Fecha:</strong> {{ $sale->created_at->format('d/m/Y h:i A') }}</p>
            <p><strong>Cliente:</strong> {{ $sale->client ? $sale->client->name : 'Público General' }}</p>
            <p><strong>Atendió:</strong> {{ $sale->user->name }}</p>
        </div>

        <div class="divider"></div>

        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Cant.</th>
                    <th style="width: 55%;">Producto</th>
                    <th style="width: 35%;" class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $item)
                <tr>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td>
                        {{ $item->product_name }}
                        <br><span style="color: #555; font-size: 9px;">x ${{ number_format($item->unit_price, 2) }}</span>
                    </td>
                    <td class="text-right">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="totals">
            <tr>
                <td class="text-right">TOTAL:</td>
                <td class="text-right" style="font-size: 14px;">${{ number_format($sale->total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" style="font-weight: normal;">Pago ({{ $sale->payment_method }}):</td>
                <td class="text-right" style="font-weight: normal;">${{ number_format($sale->paid_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" style="font-weight: normal;">
                    {{ $sale->paid_amount >= $sale->total ? 'Cambio:' : 'Pendiente:' }}
                </td>
                <td class="text-right" style="font-weight: normal;">
                    ${{ number_format(abs($sale->paid_amount - $sale->total), 2) }}
                </td>
            </tr>
        </table>

        <div class="footer">
            <p>¡Gracias por su preferencia!</p>
            <p>Este documento no es un comprobante fiscal.</p>
        </div>
    </div>
</body>
</html>