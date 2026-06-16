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
                    @if(isset($logoBase64) && $logoBase64)
                        <img src="{{ $logoBase64 }}" style="width: 150px; height: auto;">
                    @else
                        <h1 style="color: #333;">{{ $company['name'] }}</h1>
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
                        <div style="font-size: 10px; color: #555;">ORDEN DE PEDIDO</div>
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
                @php
                    // CÁLCULOS MATEMÁTICOS CLAROS PARA EL CLIENTE
                    
                    // 1. Reversión: Obtenemos el precio original antes del descuento
                    // Si no hay descuento, el original es el mismo que unit_price
                    $originalUnitPrice = ($item->discount_percent > 0) 
                        ? $item->unit_price / (1 - ($item->discount_percent / 100)) 
                        : $item->unit_price;

                    // 2. Importe total del renglón = (PrecioFinal * Cantidad) + Adicional
                    $rowSubtotal = ($item->unit_price * $item->quantity) + $item->additional_cost;
                @endphp

                <tr style="border-bottom: 1px solid #eee;">
                    <td class="text-center" style="vertical-align: middle; font-weight: bold; font-size: 13px; width: 8%;">
                        {{ $item->quantity }}
                    </td>
                    
                    <td style="width: 52%;">
                        {{-- Nombre del Producto y Color en la misma línea --}}
                        <div style="font-weight: bold; font-size: 11px;">
                            {{ $item->product_name }} -
                            <span style="background: #eee; color: #777; font-weight: normal; font-size: 10px; margin-left: 5px;">
                                Color: {{ $item->chosen_color }}
                            </span>
                        </div>

                        {{-- Detalles indentados con viñeta para orden --}}
                        <div style="margin-left: 10px; margin-top: 3px; line-height: 1.2;">
                            @if(!empty($item->custom_notes))
                                <div style="font-size: 9px; color: #555;">
                                    <span style="color: #1a4d2e;"></span> <strong>Nota:</strong> {{ $item->custom_notes }}
                                </div>
                            @endif

                            @if($item->additional_cost > 0)
                                <div style="font-size: 9px; color: #1a4d2e;">
                                    <span style="color: #1a4d2e;"></span> <strong>Cargo Adicional:</strong> ${{ number_format($item->additional_cost, 2) }}
                                </div>
                            @endif
                        </div>
                    </td>
                    
                    {{-- COLUMNA DE PRECIO UNITARIO REDISEÑADA --}}
                    <td class="text-right" style="width: 20%; vertical-align: middle;">
                        @if($item->discount_percent > 0)
                            <div style="font-size: 10px; color: #999; text-decoration: line-through;">
                                ${{ number_format($originalUnitPrice, 2) }}
                            </div>
                            <div style="display: inline-block; background: #ffebee; color: #d32f2f; font-size: 8px; font-weight: bold; padding: 1px 4px; border-radius: 3px; margin: 2px 0;">
                                DESCUENTO {{ $item->discount_percent }}%
                            </div>
                        @endif
                        <div style="font-weight: bold; font-size: 12px; color: #000; margin-top: 1px;">
                            ${{ number_format($item->unit_price, 2) }}
                        </div>
                    </td>

                    {{-- COLUMNA DE IMPORTE --}}
                    <td class="text-right" style="width: 20%; vertical-align: middle; font-weight: bold; font-size: 13px; color: #1a4d2e;">
                        ${{ number_format($rowSubtotal, 2) }}
                    </td>
                </tr>
            @endforeach
                
            </tbody>
        </table>

        <div>
            <div style="width: 55%; float: left;">
                <p><strong>Método de Pago:</strong> {{ ucfirst($sale->payment_method) }}</p>
                <p><strong>Estado del Pedido:</strong> 
                    <span style="font-weight: bold; text-transform: uppercase; color: #1a4d2e;">
                        {{-- Traducimos el 'stage' para el cliente --}}
                        @if($sale->stage == 'pedido') COTIZACIÓN / PEDIDO
                        @elseif($sale->stage == 'confirmado') CONFIRMADO / EN COLA
                        @elseif($sale->stage == 'produccion') EN FABRICACIÓN
                        @elseif($sale->stage == 'enviado') EN RUTA DE ENTREGA
                        @elseif($sale->stage == 'entregado') ENTREGADO
                        @else {{ $sale->stage }}
                        @endif
                    </span>
                </p>

                <table class="signatures" style="width: 100%; margin-top: 40px;">
                    <tr>
                        <td style="text-align: center;">
                            <div style="width: 250px; margin: 0 auto; position: relative;">
                                {{-- Renderizamos la firma digital si existe --}}
                                @if(isset($sale->signature) && $sale->signature)
                                    <img src="{{ $sale->signature }}" style="width: 200px; height: auto; margin-bottom: -15px;">
                                @else
                                    {{-- Espacio en blanco si no hay firma para que no se pegue el texto --}}
                                    <div style="height: 60px;"></div>
                                @endif
                                
                                <div class="sign-line" style="width: 100%;">
                                    <strong>FIRMA DE CONFORMIDAD</strong><br>
                                    <p style="font-size: 9px; color: #666; font-style: italic;">
                                        Al firmar esta Orden de Pedido, el cliente autoriza el inicio de fabricación de los muebles 
                                        con las especificaciones (materiales y colores) aquí descritas. Los tiempos de entrega 
                                        pueden variar según la complejidad del proceso artesanal.
                                    </p>
                                </div>
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
            Este documento es una representación impresa de una orden de pedido.
        </div>
    </div>
</body>
</html>