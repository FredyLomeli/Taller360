<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket #{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px; /* Un poco más pequeño para que quepan los descuentos */
            margin: 0; padding: 5px;
        }
        .ticket { width: 100%; max-width: 280px; margin: 0 auto; }
        
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { font-size: 14px; margin: 0; font-weight: bold; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 10px; }
        
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        
        .info { font-size: 10px; margin-bottom: 5px; }
        .info p { margin: 2px 0; }

        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        td { padding: 2px 0; vertical-align: top; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .totals { margin-top: 5px; font-weight: bold; }
        
        .footer { margin-top: 15px; text-align: center; font-size: 9px; }
        
        /* Estilos para descuentos */
        .old-price { text-decoration: line-through; color: #777; font-size: 9px; }
        .discount-badge { color: #000; font-weight: bold; font-size: 9px; display: block;}
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>{{ $company['name'] }}</h1>
            <p>{{ $company['address'] }}</p>
            <p>RFC: {{ $company['rfc'] }}</p>
            @if($company['phone'])
                <p>Tel: {{ $company['phone'] }}</p>
            @endif
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
                    <th style="width: 10%; border-bottom: 1px solid #000; text-align:left;">Cant</th>
                    <th style="width: 55%; border-bottom: 1px solid #000; text-align:left;">Descripción</th>
                    <th style="width: 35%; border-bottom: 1px solid #000;" class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalAhorro = 0;
                    $subtotalLista = 0;
                @endphp

                @foreach($sale->details as $item)
                    @php
                        // Cálculos matemáticos para mostrar el descuento
                        $precioVenta = $item->unit_price;
                        $porcentajeDesc = $item->discount_percent ?? 0;
                        
                        // Si hay descuento, calculamos el precio original (Ingeniería inversa)
                        // PrecioOriginal = PrecioVenta / (1 - %/100)
                        if ($porcentajeDesc > 0) {
                            $precioOriginal = $precioVenta / (1 - ($porcentajeDesc / 100));
                            $ahorroItem = ($precioOriginal - $precioVenta) * $item->quantity;
                        } else {
                            $precioOriginal = $precioVenta;
                            $ahorroItem = 0;
                        }

                        $totalAhorro += $ahorroItem;
                        $subtotalLista += ($precioOriginal * $item->quantity);
                    @endphp

                <tr>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td>
                        {{ $item->product_name }}
                        
                        {{-- Lógica de Precios --}}
                        <div style="margin-top: 2px;">
                            @if($porcentajeDesc > 0)
                                {{-- Precio Original Tachado --}}
                                <span class="old-price">${{ number_format($precioOriginal, 2) }}</span>
                                <br>
                                {{-- Precio Final --}}
                                <span>x ${{ number_format($precioVenta, 2) }}</span>
                                {{-- Aviso de Ahorro --}}
                                <span class="discount-badge">
                                    (Desc. {{ $porcentajeDesc }}%)
                                </span>
                            @else
                                <span style="color: #555;">x ${{ number_format($precioVenta, 2) }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right">
                        ${{ number_format($item->subtotal, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="totals">
            {{-- Si hubo descuentos, mostramos el desglose completo --}}
            @if($totalAhorro > 0)
                <tr>
                    <td class="text-right" style="color: #555; font-weight:normal;">Subtotal:</td>
                    <td class="text-right" style="color: #555; font-weight:normal;">${{ number_format($subtotalLista, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right" style="font-weight:normal;">Descuento:</td>
                    <td class="text-right" style="font-weight:normal;">-${{ number_format($totalAhorro, 2) }}</td>
                </tr>
                <tr><td colspan="2" style="height:3px;"></td></tr> {{-- Espaciador --}}
            @endif

            {{-- TOTAL FINAL --}}
            <tr>
                <td class="text-right" style="font-size: 12px;">TOTAL:</td>
                <td class="text-right" style="font-size: 14px;">${{ number_format($sale->total, 2) }}</td>
            </tr>
            
            <tr><td colspan="2" style="border-bottom: 1px dashed #000; height:5px;"></td></tr>
            
            <tr>
                <td class="text-right" style="font-weight: normal; padding-top: 5px;">
                    Pago ({{ $sale->payment_method }}):
                </td>
                <td class="text-right" style="font-weight: normal; padding-top: 5px;">
                    ${{ number_format($sale->paid_amount, 2) }}
                </td>
            </tr>
            <tr>
                <td class="text-right" style="font-weight: normal;">
                    {{ $sale->paid_amount >= $sale->total ? 'Cambio:' : 'Pendiente:' }}
                </td>
                <td class="text-right" style="font-weight: bold;">
                    ${{ number_format(abs($sale->paid_amount - $sale->total), 2) }}
                </td>
            </tr>
        </table>

        <div class="footer">
            <p>{{ $company['footer_text'] }}</p>
            <p>Este documento no es un comprobante fiscal.</p>
        </div>
    </div>
</body>
</html>