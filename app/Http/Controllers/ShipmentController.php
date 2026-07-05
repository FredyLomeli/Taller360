<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Shipment;
use App\Models\SaleDelivery;
use App\Models\SaleHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShipmentController extends Controller
{
    // Listado de viajes
    public function index()
    {
        return Inertia::render('Shipments/Index', [
            'shipments' => Shipment::with(['user', 'deliveries.saleDetail.sale.client'])->latest()->get()
        ]);
    }

    // Confirmar entrega final
    public function confirmDelivery($id)
    {
        $shipment = Shipment::findOrFail($id);
        
        DB::transaction(function () use ($shipment) {
            $shipment->update([
                'status' => 'entregado',
                'delivered_at' => now()
            ]);

            // Marcamos como 'entregado' los pedidos que ya fueron completados al 100%
            foreach ($shipment->deliveries as $delivery) {
                $detail = $delivery->saleDetail;
                $totalDelivered = $detail->deliveries()->sum('quantity_delivered');
                
                if ($totalDelivered >= $detail->quantity) {
                    $detail->sale()->update(['stage' => 'entregado']);
                }
            }
        });

        return back()->with('success', 'Viaje marcado como entregado.');
    }
    
    /**
     * Muestra la pantalla para armar un nuevo viaje (Planeación)
     */
    public function create()
    {
        // Buscamos ventas activas y calculamos cuánto se ha entregado de cada partida
        $sales = Sale::with(['client', 'details.variant.product', 'details' => function($q) {
            $q->withSum('deliveries as delivered_quantity', 'quantity_delivered');
        }])
        ->whereIn('stage', ['confirmado', 'produccion', 'enviado']) // Excluimos cancelados y entregados al 100%
        ->get()
        ->filter(function($sale) {
            // Filtro Inteligente: Solo mostramos la venta si tiene al menos 1 artículo que:
            // 1. Falte por entregar
            // 2. Tenga stock físico en almacén para poder enviarse HOY
            $hasShippableItems = false;
            foreach($sale->details as $detail) {
                $pending = $detail->quantity - ($detail->delivered_quantity ?? 0);
                $stock = $detail->variant->stock ?? 0;
                
                if ($pending > 0 && $stock > 0) {
                    $hasShippableItems = true;
                    break;
                }
            }
            return $hasShippableItems;
        })->values(); // Resetear índices para Vue

        return Inertia::render('Shipments/Create', [
            'shippableSales' => $sales
        ]);
    }

    /**
     * Procesa la creación del viaje, descuenta stock y deja historial
     */
    public function store(Request $request)
    {
        $request->validate([
            'driver_name' => 'required|string',
            'license_plate' => 'required|string',
            'destination' => 'required|string',
            'items' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            // 1. Crear el Viaje
            $shipment = Shipment::create([
                'driver_name' => $request->driver_name,
                'license_plate' => $request->license_plate,
                'destination' => $request->destination,
                'status' => 'en_transito',
                'shipped_at' => now(),
                'user_id' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                $detail = SaleDetail::with('variant')->findOrFail($item['sale_detail_id']);
                
                // 2. RESTA REAL AL INVENTARIO
                // Nos aseguramos de acceder a la variante a través del detalle
                if ($detail->variant) {
                    $detail->variant->decrement('stock', $item['quantity']);
                }

                // 3. Registrar la entrega vinculada al viaje
                SaleDelivery::create([
                    'shipment_id' => $shipment->id,
                    'sale_detail_id' => $detail->id,
                    'quantity_delivered' => $item['quantity'],
                ]);

                // 4. Registro en el Historial del Pedido (Sin cambiar el stage global)
                SaleHistory::create([
                    'sale_id' => $detail->sale_id,
                    'user_id' => auth()->id(),
                    'to_stage' => $detail->sale->stage, // Mantenemos el stage actual
                    'notes' => "📦 Envío #{$shipment->id}: {$item['quantity']} unidades de {$detail->product_name}"
                ]);
            }
        });

        return redirect()->route('shipments.index')->with('success', 'Embarque registrado y stock descontado.');
    }

    public function show($id)
    {
        $shipment = Shipment::with(['deliveries.saleDetail.sale.client'])->findOrFail($id);
        return Inertia::render('Shipments/Show', [
            'shipment' => $shipment
        ]);
    }

    public function printManifest($id)
    {
        $shipment = Shipment::with(['deliveries.saleDetail.sale.client'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.shipment_manifest', compact('shipment'));
        return $pdf->stream('remision-viaje-'.$shipment->id.'.pdf');
    }

}