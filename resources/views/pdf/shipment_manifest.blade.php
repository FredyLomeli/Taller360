<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Remisión de Embarque #{{ $shipment->id }}</title>
    <!-- Usa los mismos estilos que en tu nota de venta para mantener la identidad visual -->
    <style>
        /* [Copia aquí el mismo bloque <style> de tu nota de venta] */
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header-table { width: 100%; border-bottom: 2px solid #000; margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th { background: #1a4d2e; color: white; padding: 8px; text-transform: uppercase; }
        .items-table td { border-bottom: 1px solid #eee; padding: 8px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td><h1>REMISIÓN DE EMBARQUE #{{ $shipment->id }}</h1></td>
            <td style="text-align: right;">
                <strong>Fecha Salida:</strong> {{ $shipment->shipped_at->format('d/m/Y H:i') }}<br>
                <strong>Destino:</strong> {{ $shipment->destination }}
            </td>
        </tr>
    </table>

    <div class="client-section">
        <strong>Transporte:</strong> {{ $shipment->driver_name }} | <strong>Placas:</strong> {{ $shipment->license_plate }}
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Producto</th>
                <th>Cant.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shipment->deliveries as $del)
            <tr>
                <td>#{{ str_pad($del->saleDetail->sale_id, 6, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $del->saleDetail->product_name }}</td>
                <td>{{ $del->quantity_delivered }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures" style="margin-top: 100px;">
        <div class="sign-line">Firma de Recibido (Cliente)</div>
    </div>
</body>
</html>