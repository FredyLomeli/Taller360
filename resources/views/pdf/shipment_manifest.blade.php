<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Remisión de Viaje #{{ $shipment->id }}</title>
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
            border-bottom: 2px solid #000;
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
            color: #1a4d2e;
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
            color: #d32f2f;
        }

        /* CLIENTE Y ENVÍO */
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
            background-color: #1a4d2e;
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
            clear: both;
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
        
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @php
        // Evitamos errores de variable no definida si el controlador no pasa $company o $logoBase64
        $company = $company ?? [];
        $logoBase64 = $logoBase64 ?? null;
    @endphp

    @foreach($groupedDeliveries as $saleId => $deliveries)
        @php 
            $sale = $deliveries->first()->saleDetail?->sale;
            $client = $sale?->client;
            
            $clientName = $client->name ?? 'Mostrador / Sin Asignar';
            $clientPhone = $client->phones ?? 'N/A';
            
            $clientAddress = 'N/A';
            if ($client) {
                $addressParts = array_filter([
                    $client->street_address, 
                    $client->neighborhood, 
                    $client->city
                ]);
                $clientAddress = count($addressParts) > 0 ? implode(', ', $addressParts) : 'N/A';
            }
        @endphp

        <div class="container" style="{{ !$loop->last ? 'page-break-after: always;' : '' }}">
            
            <table class="header-table">
                <tr>
                    <td class="logo-section">
                        @if($logoBase64)
                            <img src="{{ $logoBase64 }}" class="logo-img">
                        @else
                            <h1 style="color: #1a4d2e; font-size: 24px; margin: 0;">TALLER 360</h1>
                        @endif
                    </td>
                    
                    <td class="company-info">
                        <h1 class="company-name">{{ $company['name'] ?? 'Taller 360' }}</h1>
                        <p>
                            {{ $company['address'] ?? 'Dirección de la Empresa' }}<br>
                            RFC: {{ $company['rfc'] ?? 'XAXX010101000' }}<br>
                            Tel: {{ $company['phone'] ?? 'N/A' }}
                        </p>
                    </td>

                    <td class="invoice-details">
                        <div class="folio-box">
                            <div style="font-size: 10px; color: #555;">REMISIÓN DE VIAJE</div>
                            <div class="folio-number">#{{ str_pad($shipment->id, 6, '0', STR_PAD_LEFT) }}</div>
                            <br>
                            <div>Fecha Viaje: {{ $shipment->created_at->format('d/m/Y') }}</div>
                            <div>Pedido Origen: #{{ str_pad($sale->id ?? 0, 6, '0', STR_PAD_LEFT) }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="client-section">
                <div class="section-title">Datos del Cliente y Logística de Envío</div>
                <table width="100%">
                    <tr>
                        <td width="50%">
                            <strong>Cliente:</strong> {{ $clientName }}<br>
                            <strong>Dirección:</strong> {{ $clientAddress }}<br>
                            <strong>Teléfono:</strong> {{ $clientPhone }}
                        </td>
                        <td width="50%" style="border-left: 1px solid #eee; padding-left: 10px;">
                            <strong>Chofer:</strong> {{ $shipment->driver_name ?? 'N/A' }}<br>
                            <strong>Placas:</strong> {{ $shipment->license_plate ?? 'N/A' }}<br>
                            <strong>Destino:</strong> {{ $shipment->destination ?? 'N/A' }}
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
                    @php
                        $subtotalAcumulado = 0;
                        $descuentoAcumulado = 0;
                    @endphp

                    @foreach($deliveries as $delivery)
                        @php
                            $item = $delivery->saleDetail;
                            $qty = $delivery->quantity_delivered;
                            
                            $originalUnitPrice = ($item->discount_percent > 0) 
                                ? $item->unit_price / (1 - ($item->discount_percent / 100)) 
                                : ($item->unit_price ?? 0);

                            // Subtotal renglón bruto
                            $rowBruto = $originalUnitPrice * $qty;
                            
                            // Subtotal renglón neto
                            $rowNeto = ($item->unit_price ?? 0) * $qty;
                            
                            $rowSubtotalNeto = $rowNeto; 
                            $ahorroRenglon = $rowBruto - $rowNeto;
                            
                            $subtotalAcumulado += $rowBruto;
                            $descuentoAcumulado += $ahorroRenglon;
                        @endphp
                        <tr style="border-bottom: 1px solid #eee;">
                            <td class="text-center" style="vertical-align: middle; font-weight: bold; font-size: 13px;">
                                {{ $qty }}
                            </td>
                            <td>
                                <div style="font-weight: bold; font-size: 11px;">
                                    {{ $item->product_name ?? 'N/A' }} -
                                    <span style="background: #eee; color: #777; font-weight: normal; font-size: 10px; margin-left: 5px;">
                                        Color: {{ $item->chosen_color ?? '-' }}
                                    </span>
                                </div>
                                <div style="margin-left: 10px; margin-top: 3px; line-height: 1.2;">
                                    @if(!empty($item->custom_notes))
                                        <div style="font-size: 9px; color: #555;">
                                            <strong>Nota:</strong> {{ $item->custom_notes }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right" style="vertical-align: middle;">
                                @if($item->discount_percent > 0)
                                    <div style="font-size: 10px; color: #999; text-decoration: line-through;">
                                        ${{ number_format($originalUnitPrice, 2) }}
                                    </div>
                                    <div style="display: inline-block; background: #ffebee; color: #d32f2f; font-size: 8px; font-weight: bold; padding: 1px 4px; border-radius: 3px; margin: 2px 0;">
                                        DESC. {{ $item->discount_percent }}%
                                    </div>
                                @endif
                                <div style="font-weight: bold; font-size: 12px; color: #000; margin-top: 1px;">
                                    ${{ number_format($item->unit_price ?? 0, 2) }}
                                </div>
                            </td>
                            <td class="text-right" style="vertical-align: middle; font-weight: bold; font-size: 13px; color: #1a4d2e;">
                                ${{ number_format($rowSubtotalNeto, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div>
                <div style="width: 55%; float: left;">
                    <p><strong>Pedido Origen:</strong> #{{ str_pad($sale->id ?? 0, 6, '0', STR_PAD_LEFT) }}</p>
                    <p><strong>Estado del Pedido:</strong> 
                        <span style="font-weight: bold; text-transform: uppercase; color: #1a4d2e;">
                            REMISIÓN DE ENTREGA (VIAJE #{{ $shipment->id }})
                        </span>
                    </p>

                    <table class="signatures">
                        <tr>
                            <td style="text-align: center;">
                                <div class="sign-line">
                                    <strong>NOMBRE Y FIRMA DE RECEPCIÓN</strong><br>
                                    <p style="font-size: 9px; color: #666; font-style: italic;">
                                        Firma de conformidad sobre las cantidades y el estado físico de los productos entregados.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                @php
                    $totalGeneralEnvio = $subtotalAcumulado - $descuentoAcumulado;
                @endphp

                <table class="totals-table">
                    <tr>
                        <td class="text-right">Subtotal de esta Remisión</td>
                        <td class="text-right">${{ number_format($subtotalAcumulado, 2) }}</td>
                    </tr>
                    @if($descuentoAcumulado > 0)
                    <tr>
                        <td class="text-right" style="color: #d32f2f;">Descuento Remisión</td>
                        <td class="text-right" style="color: #d32f2f;"> - ${{ number_format($descuentoAcumulado, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td class="text-right">TOTAL ESTE VIAJE</td>
                        <td class="text-right">${{ number_format($totalGeneralEnvio, 2) }}</td>
                    </tr>
                    
                    <tr>
                        <td colspan="2" style="height: 15px; border: none;"></td>
                    </tr>

                    <tr>
                        <td class="text-right" style="font-size: 11px;">Total Global del Pedido</td>
                        <td class="text-right" style="font-size: 11px;">${{ number_format($sale->total ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right text-gray-500" style="font-size: 11px;">Anticipos Previos</td>
                        <td class="text-right" style="font-size: 11px;">${{ number_format($sale->paid_amount ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-right text-gray-500" style="font-size: 11px;">Saldo Restante Pedido</td>
                        @php
                            $saldoPendiente = max(0, ($sale->total ?? 0) - ($sale->paid_amount ?? 0));
                        @endphp
                        <td class="text-right" style="font-size: 11px; color: {{ $saldoPendiente > 0 ? 'red' : 'black' }};">
                            ${{ number_format($saldoPendiente, 2) }}
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                {{ $company['footer_text'] ?? 'Gracias por su preferencia.' }}
                <br>
                Este documento es una representación impresa de un manifiesto de entrega (remisión).
            </div>

        </div>
    @endforeach
</body>
</html>