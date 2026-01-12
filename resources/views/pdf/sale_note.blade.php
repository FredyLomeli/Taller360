<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de Venta #{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        
        /* CABECERA */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #000; /* Línea negra gruesa */
            padding-bottom: 10px;
        }
        .logo-section {
            width: 20%;
            vertical-align: top;
        }
        .logo-img {
            max-width: 120px;
            max-height: 80px;
        }
        .company-info {
            width: 50%;
            vertical-align: top;
            padding-left: 15px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a4d2e; /* Verde corporativo */
            margin: 0;
        }
        .invoice-details {
            width: 30%;
            text-align: right;
            vertical-align: top;
        }
        .folio-box {
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .folio-number {
            font-size: 16px;
            font-weight: bold;
            color: #d32f2f; /* Rojo para el folio */
        }

        /* CLIENTE */
        .client-section {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 5px;
            background-color: #fff;
        }
        .section-title {
            font-weight: bold;
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        /* TABLA PRODUCTOS */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #1a4d2e; /* Verde encabezado */
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* TOTALES */
        .totals-table {
            width: 40%;
            float: right;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .total-row td {
            font-weight: bold;
            font-size: 14px;
            background-color: #f0f0f0;
            border-top: 2px solid #333;
        }

        /* PIE DE PÁGINA */
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 10px;
            color: #777;
            clear: both; /* Limpiar flotados */
        }
        .signatures {
            margin-top: 60px;
            margin-bottom: 30px;
            width: 100%;
        }
        .sign-line {
            width: 40%;
            border-top: 1px solid #000;
            margin: 0 auto;
            text-align: center;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <table class="header-table">
            <tr>
                <td class="logo-section">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" class="logo-img">
                    @else
                        <div style="width: 80px; height: 80px; background: #eee; text-align: center; line-height: 80px; color: #999;">SIN LOGO</div>
                    @endif
                </td>
                
                <td class="company-info">
                    <h1 class="company-name">{{ $company['name'] ?? 'Mi Empresa' }}</h1>
                    <p>
                        {{ $company['address'] }}<br>
                        RFC: {{ $company['rfc'] }}<br>
                        Tel: {{ $company['phone'] }}
                    </p>
                </td>

                <td class="invoice-details">
                    <div class="folio-box">
                        <div style="font-size: 10px; color: #555;">NOTA DE VENTA</div>
                        <div class="folio-number">#{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</div>
                        <br>
                        <div>Fecha: {{ $sale->created_at->format('d/m/Y') }}</div>
                        <div>Hora: {{ $sale->created_at->format('h:i A') }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="client-section">
            <div class="section-title">Datos del Cliente</div>
            <table width="100%">
                <tr>
                    <td width="60%">
                        <strong>Cliente:</strong> {{ $sale->client ? $sale->client->name : 'Público en General' }}<br>
                        <strong>Dirección:</strong> {{ $sale->client ? ($sale->client->address ?? 'N/A') : '-' }}
                    </td>
                    <td width="40%">
                        <strong>Teléfono:</strong> {{ $sale->client ? ($sale->client->phone ?? 'N/A') : '-' }}<br>
                        <strong>Email:</strong> {{ $sale->client ? ($sale->client->email ?? 'N/A') : '-' }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="10%" class="text-center">Cant.</th>
                    <th width="50%">Descripción</th>
                    <th width="20%" class="text-right">Precio U.</th>
                    <th width="20%" class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $item)
                <tr>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td>
                        {{ $item->product_name }}
                        
                        @if(isset($item->description))
                            <br><span style="font-size: 10px; color: #666;">{{ Str::limit($item->description, 50) }}</span>
                        @endif

                        @if($item->discount_percent > 0)
                            <br>
                            <span style="font-size: 10px; color: #d32f2f;">
                                Descuento: {{ $item->discount_percent }}% 
                                (Ahorras: ${{ number_format( ($item->unit_price / (1 - ($item->discount_percent/100))) - $item->unit_price , 2) }})
                            </span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->discount_percent > 0)
                            @php 
                                $originalPrice = $item->unit_price / (1 - ($item->discount_percent / 100)); 
                            @endphp
                            <span style="text-decoration: line-through; color: #999; font-size: 10px;">
                                ${{ number_format($originalPrice, 2) }}
                            </span>
                            <br>
                        @endif
                        ${{ number_format($item->unit_price, 2) }}
                    </td>
                    <td class="text-right font-bold">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
                
                @for($i = 0; $i < max(0, 5 - count($sale->details)); $i++)
                <tr>
                    <td style="color: white;">.</td>
                    <td></td><td></td><td></td>
                </tr>
                @endfor
            </tbody>
        </table>

        <div>
            <div style="width: 55%; float: left;">
                <p><strong>Método de Pago:</strong> {{ ucfirst($sale->payment_method) }}</p>
                <p><strong>Estado:</strong> 
                    <span style="color: {{ $sale->status == 'pagado' ? 'green' : 'red' }}; font-weight: bold; text-transform: uppercase;">
                        {{ $sale->status }}
                    </span>
                </p>

                <table class="signatures">
                    <tr>
                        <td>
                            <div class="sign-line">
                                Firma de Conformidad<br>
                                <span style="font-size: 9px; font-weight: normal;">Recibí la mercancía a mi entera satisfacción</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            @php
                $totalAhorrado = 0;
                foreach($sale->details as $item) {
                    if($item->discount_percent > 0) {
                        // Reversa matemática: Si pagué $90 y fue 10% desc, el original era $100.
                        // Fórmula: PrecioFinal / (1 - (Porcentaje / 100))
                        $precioOriginalUnitario = $item->unit_price / (1 - ($item->discount_percent / 100));
                        
                        // Cuánto se ahorró por unidad * cantidad
                        $ahorroItem = ($precioOriginalUnitario - $item->unit_price) * $item->quantity;
                        $totalAhorrado += $ahorroItem;
                    }
                }
                // El Subtotal "Bruto" sería lo que pagó + lo que se ahorró
                $subtotalBruto = $sale->total + $totalAhorrado;
            @endphp

            <table class="totals-table">
                
                <tr>
                    <td class="text-right">Subtotal</td>
                    <td class="text-right">${{ number_format($subtotalBruto, 2) }}</td>
                </tr>

                @if($totalAhorrado > 0)
                <tr>
                    <td class="text-right" style="color: #d32f2f;">Descuento Total</td>
                    <td class="text-right" style="color: #d32f2f;"> - ${{ number_format($totalAhorrado, 2) }}</td>
                </tr>
                @endif

                <tr class="total-row">
                    <td class="text-right">TOTAL</td>
                    <td class="text-right">${{ number_format($sale->total, 2) }}</td>
                </tr>
                
                <tr>
                    <td class="text-right text-gray-500">Monto Pagado</td>
                    <td class="text-right">${{ number_format($sale->paid_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right text-gray-500">
                        {{ $sale->paid_amount >= $sale->total ? 'Cambio' : 'Pendiente' }}
                    </td>
                    <td class="text-right" style="color: {{ ($sale->paid_amount < $sale->total) ? 'red' : 'black' }}">
                        ${{ number_format(abs($sale->paid_amount - $sale->total), 2) }}
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            {{ $company['footer_text'] ?? '¡Gracias por su compra!' }}
            <br>
            Este documento es una representación impresa de una nota de venta.
        </div>
    </div>
</body>
</html>