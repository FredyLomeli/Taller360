<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Shipment;
use App\Models\SaleDelivery;
use App\Models\SaleHistory;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShipmentController extends Controller
{
    public function index()
    {
        return Inertia::render('Shipments/Index', [
            'shipments' => Shipment::select('id', 'driver_name', 'license_plate', 'destination', 'status', 'pickup_type', 'created_at')
                ->latest()
                ->get()
        ]);
    }
    /**
     * Muestra la pantalla para armar un nuevo viaje (Planeación)
     */
    public function create(Request $request)
    {
        $clientIds = $request->input('client_ids', []);
        // Buscamos ventas activas y calculamos cuánto se ha entregado de cada partida
        $salesQuery = Sale::select('id', 'user_id', 'client_id', 'stage', 'promised_date', 'created_at')
            ->with([
                'client',
                'details' => function ($q) {
                    // Solo lo que Shipments/Create.vue realmente usa — nunca precios, nunca firma.
                    $q->select('id', 'sale_id', 'product_variant_id', 'product_name', 'quantity', 'chosen_color')
                    ->withSum(['deliveries as delivered_quantity' => function ($dq) {
                        $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
                    }], 'quantity_delivered');
                },
                'details.variant:id,product_id,material,measurements,stock',
                'details.variant.product:id,name,image',
            ])
            ->whereIn('stage', ['confirmado', 'produccion', 'enviado']);

        if (!empty($clientIds)) {
            $salesQuery->whereIn('client_id', $clientIds);
        }

        $sales = $salesQuery->get()->filter(function($sale) {
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
            'pickup_type' => 'nullable|in:flota_propia,recoleccion_cliente',
            'items' => 'required|array',
        ]);

        $isCounterPickup = ($request->input('pickup_type', 'flota_propia') === 'recoleccion_cliente');

        try {
            DB::transaction(function () use ($request, $isCounterPickup) {
                // 1. Crear el Viaje
                $shipment = Shipment::create([
                    'driver_name' => $request->driver_name,
                    'license_plate' => $request->license_plate,
                    'destination' => $request->destination,
                    'pickup_type' => $request->input('pickup_type', 'flota_propia'),
                    'status' => $isCounterPickup ? 'entregado' : 'en_transito',
                    'shipped_at' => now(),
                    'delivered_at' => $isCounterPickup ? now() : null,
                    'user_id' => auth()->id(),
                ]);

                $allowNegative = Setting::where('key', 'allow_negative_stock')->value('value');

                foreach ($request->items as $item) {
                    $detail = SaleDetail::with(['variant', 'sale'])->findOrFail($item['sale_detail_id']);

                    if ($detail->variant) {

                        $variant = ProductVariant::lockForUpdate()->find($detail->variant->id);

                        if (!$allowNegative && $variant->stock < $item['quantity']) {
                            throw new \Exception("Stock insuficiente de {$detail->product_name}. Disponible real: {$variant->stock}, solicitado: {$item['quantity']}.");
                        }

                        $variant->decrement('stock', $item['quantity']);
                    }

                    // 3. Registrar la entrega vinculada al viaje
                    SaleDelivery::create([
                        'shipment_id' => $shipment->id,
                        'sale_detail_id' => $detail->id,
                        'quantity_delivered' => $item['quantity'],
                    ]);

                    if ($isCounterPickup) {
                        $this->closeOrderIfComplete($detail);
                    } elseif (!in_array($detail->sale->stage, ['enviado', 'entregado'])) {
                        $detail->sale->update(['stage' => 'enviado']);
                    }

                    // 4. Registro en el Historial del Pedido (Sin cambiar el stage global)
                    SaleHistory::create([
                        'sale_id' => $detail->sale_id,
                        'user_id' => auth()->id(),
                        'to_stage' => $detail->sale->fresh()->stage,
                        'notes' => $isCounterPickup
                            ? "🏬 Recolección en mostrador: {$item['quantity']} unidades de {$detail->product_name}"
                            : "📦 Envío #{$shipment->id}: {$item['quantity']} unidades de {$detail->product_name}"
                    ]);
                }
            });
            
            return redirect()->route('shipments.index')->with('success', 'Embarque registrado y stock descontado.');
        } 
        catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $shipment = Shipment::select('id', 'driver_name', 'license_plate', 'status', 'pickup_type', 'created_at')
            ->with([
                'deliveries' => function ($q) {
                    $q->select('id', 'shipment_id', 'sale_detail_id', 'quantity_delivered');
                },
                'deliveries.saleDetail:id,sale_id,product_name,chosen_color',
                'deliveries.saleDetail.sale:id,client_id',
                'deliveries.saleDetail.sale.client:id,name',
            ])
            ->findOrFail($id);

        return Inertia::render('Shipments/Show', ['shipment' => $shipment]);
    }

    public function printManifest($id)
    {
        $shipment = Shipment::with(['deliveries.saleDetail.sale.client'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.shipment_manifest', compact('shipment'));
        return $pdf->stream('remision-viaje-'.$shipment->id.'.pdf');
    }
    
    /**
     * Revisa TODAS las líneas del pedido (no solo la que se acaba de entregar) y lo marca
     * 'entregado' únicamente si el 100% de cada línea ya se entregó. Reutilizado tanto por
     * confirmDelivery() (flota propia) como por store() (recolección en mostrador).
     */
    private function closeOrderIfComplete(SaleDetail $detail): void
    {
        $sale = $detail->sale()->with(['details' => function ($q) {
            $q->withSum(['deliveries as delivered_quantity' => function ($dq) {
                $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
            }], 'quantity_delivered');
        }])->first();

        $allDelivered = $sale->details->every(function ($d) {
            return ($d->delivered_quantity ?? 0) >= $d->quantity;
        });

        if ($allDelivered) {
            $sale->update(['stage' => 'entregado']);
        }
    }

    public function confirmDelivery($id)
    {
        $shipment = Shipment::with('deliveries.saleDetail')->findOrFail($id);

        if ($shipment->status !== 'en_transito') {
            return back()->withErrors(['error' => 'Este embarque no está en tránsito.']);
        }

        DB::transaction(function () use ($shipment) {
            $shipment->update(['status' => 'entregado', 'delivered_at' => now()]);

            foreach ($shipment->deliveries as $delivery) {
                $this->closeOrderIfComplete($delivery->saleDetail);
            }
        });

        return back()->with('success', 'Viaje marcado como entregado.');
    }

    public function cancel($id)
    {
        $shipment = Shipment::with('deliveries.saleDetail.sale')->findOrFail($id);

        if ($shipment->status === 'cancelado') {
            return back()->withErrors(['error' => 'Este embarque ya está cancelado.']);
        }
        if ($shipment->pickup_type === 'flota_propia' && $shipment->status === 'entregado') {
            return back()->withErrors(['error' => 'No se puede cancelar un embarque de flota propia ya entregado.']);
        }

        DB::transaction(function () use ($shipment) {
            foreach ($shipment->deliveries as $delivery) {
                $detail = $delivery->saleDetail;
                if (!$detail) continue;

                if ($detail->variant) {
                    $detail->variant->increment('stock', $delivery->quantity_delivered);
                }

                $sale = $detail->sale;

                if (in_array($sale->stage, ['entregado', 'enviado'])) {
                    $transition = SaleHistory::where('sale_id', $sale->id)
                        ->whereIn('to_stage', ['entregado', 'enviado'])
                        ->latest()
                        ->first();

                    $revertStage = $transition->from_stage ?? 'produccion';
                    $sale->update(['stage' => $revertStage]);
                }

                SaleHistory::create([
                    'sale_id' => $sale->id,
                    'user_id' => auth()->id(),
                    'to_stage' => $sale->fresh()->stage,
                    'notes' => "❌ Embarque #{$shipment->id} cancelado: se regresan {$delivery->quantity_delivered} unidades de {$detail->product_name} a inventario."
                ]);
            }

            $shipment->update(['status' => 'cancelado']);
        });

        return back()->with('success', 'Embarque cancelado y stock restituido correctamente.');
    }

}