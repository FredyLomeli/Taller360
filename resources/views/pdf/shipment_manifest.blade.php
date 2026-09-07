<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Remisión de Embarque #{{ $shipment->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .page-container { width: 100%; position: relative; }
        .header-table { width: 100%; border-bottom: 2px solid #1a4d2e; margin-bottom: 15px; padding-bottom: 10px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        .items-table th { background: #1a4d2e; color: white; padding: 6px; text-transform: uppercase; font-size: 10px; }
        .items-table td { border-bottom: 1px solid #ddd; padding: 6px; font-size: 11px; }
        .client-banner { background-color: #f3f4f6; padding: 8px; margin-top: 15px; font-weight: bold; border-left: 4px solid #1a4d2e; text-transform: uppercase; font-size: 12px; }
        .order-title { margin-top: 10px; font-weight: bold; padding-left: 5px; color: #1a4d2e; font-size: 11px; }
        .signatures-table { width: 100%; margin-top: 40px; text-align: center; page-break-inside: avoid; }
        .signatures-table td { width: 33%; vertical-align: bottom; padding: 0 10px; }
        .sign-line { border-top: 1px solid #000; margin-top: 35px; padding-top: 5px; font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>
    @foreach($groupedDeliveries as $clientId => $clientOrders)
        @php
            $firstDelivery = $clientOrders->first()->first();
            $client = $firstDelivery->saleDetail?->sale?->client;
            $clientName = $client ? $client->name : 'Venta de Mostrador';
            $clientBusiness = $client && $client->business_name ? ' ('.$client->business_name.')' : '';
        @endphp

        <div class="page-container">
            <!-- Encabezado del Embarque -->
            <table class="header-table">
                <tr>
                    <td>
                        <h2 style="margin: 0; color: #1a4d2e;">REMISIÓN DE EMBARQUE #{{ $shipment->id }}</h2>
                        <span style="font-size: 10px; color: #666;">Hoja de Recepción de Mercancía</span>
                    </td>
                    <td style="text-align: right;">
                        <strong>Fecha Salida:</strong> {{ $shipment->shipped_at ? \Carbon\Carbon::parse($shipment->shipped_at)->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}<br>
                        <strong>Destino:</strong> {{ $shipment->destination ?? 'N/A' }}
                    </td>
                </tr>
            </table>

            <div style="margin-bottom: 10px; font-size: 11px;">
                <strong>Transporte:</strong> {{ $shipment->driver_name ?? 'N/A' }} &nbsp;|&nbsp; <strong>Placas:</strong> {{ $shipment->license_plate ?? 'N/A' }}
            </div>

            <!-- Banner del Cliente -->
            <div class="client-banner">
                CLIENTE: {{ $clientName }}{{ $clientBusiness }}
            </div>
            
            @foreach($clientOrders as $saleId => $deliveries)
                @php
                    $firstDel = $deliveries->first();
                    $promisedDate = $firstDel->saleDetail?->sale?->promised_date;
                    $promisedText = $promisedDate ? \Carbon\Carbon::parse($promisedDate)->format('d/m/Y') : 'N/A';
                @endphp
                
                <div class="order-title">
                    Pedido #{{ str_pad($saleId, 5, '0', STR_PAD_LEFT) }} &nbsp;|&nbsp; Fecha Compromiso: {{ $promisedText }}
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Producto</th>
                            <th style="width: 15%; text-align: center;">Medida</th>
                            <th style="width: 30%;">Color / Material</th>
                            <th style="width: 10%; text-align: center;">Cant.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $del)
                        <tr>
                            <td>{{ $del->saleDetail?->product_name }}</td>
                            <td style="text-align: center;">{{ $del->saleDetail?->variant?->measurements ?? 'N/A' }}</td>
                            <td>{{ $del->saleDetail?->chosen_color ?? 'N/A' }} / {{ $del->saleDetail?->variant?->material ?? 'N/A' }}</td>
                            <td style="text-align: center; font-weight: bold;">{{ $del->quantity_delivered }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            <!-- Bloque Formal de Cierre de Entrega / Firmas -->
            <table class="signatures-table">
                <tr>
                    <td><div class="sign-line">Nombre de quien recibe</div></td>
                    <td><div class="sign-line">Fecha y hora de entrega</div></td>
                    <td><div class="sign-line">Firma de Conformidad</div></td>
                </tr>
            </table>
        </div>

        @if(!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
</body>
</html>