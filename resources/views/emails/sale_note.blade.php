<!DOCTYPE html>
<html>
<head>
    <title>Nota de Venta</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2>¡Gracias por tu compra!</h2>
    
    <p>Hola <strong>{{ $sale->client ? $sale->client->name : 'Cliente' }}</strong>,</p>
    
    <p>Adjunto encontrarás el detalle de tu compra realizada el {{ $sale->created_at->format('d/m/Y') }}.</p>
    
    <p><strong>Total:</strong> ${{ number_format($sale->total, 2) }}</p>
    
    <hr>
    <p style="font-size: 12px; color: #777;">
        Si tienes dudas, contáctanos respondiendo a este correo.
    </p>
</body>
</html>