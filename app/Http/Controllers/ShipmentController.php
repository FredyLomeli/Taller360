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
        
        // Extraemos clientes disponibles ANTES del filtro
        $availableClients = Sale::whereIn('stage', ['confirmado', 'produccion', 'enviado'])
            ->with('client:id,name,business_name')
            ->get()
            ->pluck('client')
            ->unique('id')
            ->filter()
            ->values();

        // Buscamos ventas activas y calculamos cuánto se ha entregado de cada partida
        $salesQuery = Sale::select('id', 'user_id', 'client_id', 'stage', 'promised_date', 'created_at')
            ->with([
                'client',
                'details' => function ($q) {
                    // Solo lo que Shipments/Create.vue realmente usa — nunca precios, nunca firma.
                    $q->select('id', 'sale_id', 'product_variant_id', 'product_name', 'quantity', 'chosen_color')
                    ->withSum(['deliveries as delivered_quantity' => function ($dq) {
                        $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
                    }], 'quantity_delivered')
                    ->withSum('detalladoRecords as raw_detailed_quantity', 'quantity');
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
                // Cálculo dinámico del remanente en detallado real (excluyendo lo ya embarcado)
                $total_detallado = $detail->raw_detailed_quantity ?? 0;
                $total_entregado = $detail->delivered_quantity ?? 0;
                
                $detail->detailed_quantity = max(0, $total_detallado - $total_entregado);

                $pending = $detail->quantity - ($detail->delivered_quantity ?? 0);
                $stock = $detail->variant->stock ?? 0;
                
                if ($pending > 0 && $stock > 0) {
                    $hasShippableItems = true;
                }
            }
            return $hasShippableItems;
        })->values(); // Resetear índices para Vue

        return Inertia::render('Shipments/Create', [
            'shippableSales' => $sales,
            'availableClients' => $availableClients,
            'filters' => ['client_ids' => $clientIds]
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
                        // Bloqueo de actualización explícito para evitar condiciones de carrera
                        $variant = ProductVariant::where('id', $detail->variant->id)->lockForUpdate()->first();

                        if (!$allowNegative && $variant->stock < $item['quantity']) {
                            throw new \Exception("Stock insuficiente de {$detail->product_name}. Disponible real: {$variant->stock}, solicitado: {$item['quantity']}.");
                        }

                        // Calcular piezas apartadas para descontar directo del límite reservado
                        $cantidad_a_descontar_reserva = min($item['quantity'], $variant->reserved_stock);

                        // Doble deducción atómica de inventario
                        $variant->decrement('stock', $item['quantity']);
                        
                        if ($cantidad_a_descontar_reserva > 0) {
                            $variant->decrement('reserved_stock', $cantidad_a_descontar_reserva);
                        }
                    }

                    // 3. Registrar la entrega vinculada al viaje
                    SaleDelivery::create([
                        'shipment_id' => $shipment->id,
                        'sale_detail_id' => $detail->id,
                        'quantity_delivered' => $item['quantity'],
                    ]);

                    $totalDelivered = SaleDelivery::where('sale_detail_id', $detail->id)->sum('quantity_delivered');
                    $totalCompleted = \App\Models\ProductionCompletion::where('sale_detail_id', $detail->id)->sum('quantity_completed');
                    
                    if (($detail->quantity - $totalDelivered > 0) && ($detail->quantity - $totalCompleted > 0)) {
                        $detail->update(['production_hold' => true]);
                    }

                    if ($isCounterPickup) {
                        $this->closeOrderIfComplete($detail, 'entregado');
                    } elseif (!in_array($detail->sale->stage, ['enviado', 'entregado'])) {
                        // Solo cambiaremos a enviado si se completa todo el pedido
                        $this->closeOrderIfComplete($detail, 'enviado');
                    }
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
        $shipment = Shipment::with(['deliveries.saleDetail.sale.client', 'user'])->findOrFail($id);
        
        $groupedDeliveries = $shipment->deliveries->groupBy(function ($delivery) {
            return $delivery->saleDetail?->sale?->client_id ?? 'mostrador';
        })->map(function ($clientGroup) {
            return $clientGroup->groupBy(function ($delivery) {
                return $delivery->saleDetail?->sale_id ?? 0;
            });
        });

        $pdf = Pdf::loadView('pdf.shipment_manifest', compact('shipment', 'groupedDeliveries'));
        return $pdf->stream('remision-viaje-'.$shipment->id.'.pdf');
    }
    
    /**
     * Revisa TODAS las líneas del pedido (no solo la que se acaba de entregar) y lo marca
     * 'entregado' únicamente si el 100% de cada línea ya se entregó. Reutilizado tanto por
     * confirmDelivery() (flota propia) como por store() (recolección en mostrador).
     */
    private function closeOrderIfComplete(SaleDetail $detail, string $targetStage = 'entregado'): void
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
            $sale->update(['stage' => $targetStage]);
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
            $salesToRecalculate = [];

            foreach ($shipment->deliveries as $delivery) {
                $detail = $delivery->saleDetail;
                if (!$detail) continue;

                if ($detail->variant) {
                    $detail->variant->increment('stock', $delivery->quantity_delivered);
                }

                $salesToRecalculate[$detail->sale_id] = $detail->sale;
            }

            $shipment->update(['status' => 'cancelado']);

            foreach ($salesToRecalculate as $sale) {
                // Recálculo en vivo de las piezas entregadas vs totales (excluyendo este embarque ya cancelado)
                $saleWithDetails = Sale::with(['details' => function ($q) {
                    $q->withSum(['deliveries as delivered_quantity' => function ($dq) {
                        $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
                    }], 'quantity_delivered');
                }])->find($sale->id);

                $hasPendingItems = $saleWithDetails->details->contains(function ($d) {
                    return ($d->delivered_quantity ?? 0) < $d->quantity;
                });

                if ($hasPendingItems && in_array($sale->stage, ['entregado', 'enviado'])) {
                    $sale->update(['stage' => 'produccion']);
                }

                SaleHistory::create([
                    'sale_id' => $sale->id,
                    'user_id' => auth()->id(),
                    'to_stage' => $sale->fresh()->stage,
                    'notes' => "❌ Embarque #{$shipment->id} cancelado. Se restituyó stock."
                ]);
            }
        });

        return back()->with('success', 'Embarque cancelado y stock restituido correctamente.');
    }

}