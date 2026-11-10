<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Color;
use App\Models\DamagedStockLog;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDelivery;
use App\Models\SaleDetail;
use App\Models\SaleHistory;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\VariantColorStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleReturnController extends Controller
{
    public function index()
    {
        $returns = SaleReturn::with([
            'client:id,name,phones,business_name',
            'user:id,name',
            'items' => function ($q) {
                $q->with([
                    'saleDetail:id,sale_id,product_name,chosen_color',
                    'saleDetail.sale:id,client_id,stage',
                    'saleDetail.variant:id,material,measurements',
                    'shipment:id,driver_name,created_at',
                    'colorRelation:id,name,hex',
                ]);
            }
        ])->latest('returned_at')->get()->map(function ($ret) {
            $ret->sale_ids = $ret->items->pluck('saleDetail.sale_id')->filter()->unique()->values();
            return $ret;
        });

        $stats = [
            'total_returns' => $returns->count(),
            'total_items' => (int) $returns->sum(fn($r) => $r->items->sum('quantity')),
            'damaged_items' => (int) $returns->sum(fn($r) => $r->items->where('disposition', 'danada')->sum('quantity')),
            'scrapped_items' => (int) $returns->sum(fn($r) => $r->items->where('disposition', 'baja')->sum('quantity')),
            'wrong_color_items' => (int) $returns->sum(fn($r) => $r->items->where('disposition', 'color_equivocado')->sum('quantity')),
        ];

        return Inertia::render('Shipments/Returns/Index', [
            'returns' => $returns,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request)
    {
        $clientId = $request->query('client_id');

        // Clientes con ventas que tienen entregas en embarques no cancelados
        $clients = Client::select('id', 'name', 'phones', 'business_name')
            ->whereHas('sales.details.deliveries.shipment', function ($q) {
                $q->where('status', '!=', 'cancelado');
            })
            ->orderBy('name')
            ->get();

        $returnableDeliveries = [];

        if ($clientId) {
            $deliveries = SaleDelivery::whereHas('saleDetail.sale', fn($q) => $q->where('client_id', $clientId))
                ->whereHas('shipment', fn($q) => $q->where('status', '!=', 'cancelado'))
                ->with([
                    'shipment:id,driver_name,license_plate,created_at,pickup_type',
                    'saleDetail' => function ($q) {
                        $q->with([
                            'sale:id,client_id,created_at',
                            'variant.materialModel',
                            'variant.colorStocks:id,product_variant_id',
                            'colorRelation:id,name,hex',
                        ]);
                    }
                ])
                ->get();

            // Pre-agrupamos todas las devoluciones de estas entregas en una sola consulta indexada
            $saleDetailIds = $deliveries->pluck('sale_detail_id')->unique()->filter();
            $shipmentIds = $deliveries->pluck('shipment_id')->unique()->filter();

            $returnedMap = SaleReturnItem::whereIn('sale_detail_id', $saleDetailIds)
                ->whereIn('shipment_id', $shipmentIds)
                ->groupBy('sale_detail_id', 'shipment_id')
                ->select('sale_detail_id', 'shipment_id', DB::raw('SUM(quantity) as total_returned'))
                ->get()
                ->keyBy(fn($item) => $item->sale_detail_id . '-' . $item->shipment_id);

            foreach ($deliveries as $deliv) {
                $mapKey = $deliv->sale_detail_id . '-' . $deliv->shipment_id;
                $alreadyReturned = (int) ($returnedMap->get($mapKey)?->total_returned ?? 0);

                $netAvailable = max(0, $deliv->quantity_delivered - $alreadyReturned);

                if ($netAvailable > 0) {
                    $detail = $deliv->saleDetail;
                    $isMelamina = ($detail->variant?->materialModel ? $detail->variant->materialModel->stock_por_color : ($detail->variant?->material === 'Melamina')) || ($detail->variant?->colorStocks && $detail->variant->colorStocks->isNotEmpty());

                    $returnableDeliveries[] = [
                        'delivery_id' => $deliv->id,
                        'shipment_id' => $deliv->shipment_id,
                        'shipment_folio' => '#' . str_pad($deliv->shipment_id, 4, '0', STR_PAD_LEFT),
                        'shipment_driver' => $deliv->shipment?->driver_name,
                        'shipment_date' => $deliv->shipment?->created_at?->format('d/m/Y'),
                        'sale_id' => $detail->sale_id,
                        'sale_detail_id' => $detail->id,
                        'product_name' => $detail->product_name,
                        'material' => $detail->variant?->material ?? 'Madera',
                        'measurements' => $detail->variant?->measurements,
                        'chosen_color' => $detail->chosen_color,
                        'color_id' => $detail->color_id,
                        'color_hex' => $detail->colorRelation?->hex,
                        'quantity_delivered' => $deliv->quantity_delivered,
                        'already_returned' => $alreadyReturned,
                        'net_available' => $netAvailable,
                        'is_melamina' => (bool)$isMelamina,
                    ];
                }
            }
        }

        $colors = Color::where('activo', true)->orderBy('name')->get(['id', 'name', 'hex']);

        return Inertia::render('Shipments/Returns/Create', [
            'clients' => $clients,
            'selectedClientId' => $clientId ? (int)$clientId : null,
            'returnableDeliveries' => $returnableDeliveries,
            'colors' => $colors,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'authorized_by' => 'required|string|min:1|max:255',
            'returned_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sale_detail_id' => 'required|exists:sale_details,id',
            'items.*.shipment_id' => 'nullable|exists:shipments,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.reason_type' => 'required|string|max:50',
            'items.*.disposition' => 'required|string|in:danada,baja,color_equivocado,buen_estado',
            'items.*.color_id' => 'nullable|exists:colors,id',
            'items.*.chosen_color' => 'nullable|string|max:100',
            'items.*.notes' => 'nullable|string|max:1000',
        ], [
            'authorized_by.required' => 'La persona que autoriza la devolución es obligatoria.',
            'items.required' => 'Debe incluir al menos una partida a devolver.',
            'items.min' => 'Debe incluir al menos una partida a devolver.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $year = now()->format('Y');

            // Folio consecutivo atómico DEV-YYYY-XXXX
            $latestReturn = SaleReturn::where('folio', 'like', "DEV-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $sequence = 1;
            if ($latestReturn && preg_match('/DEV-\d{4}-(\d{4})/', $latestReturn->folio, $matches)) {
                $sequence = intval($matches[1]) + 1;
            }
            $folio = sprintf('DEV-%s-%04d', $year, $sequence);

            $saleReturn = SaleReturn::create([
                'folio' => $folio,
                'client_id' => $validated['client_id'],
                'user_id' => auth()->id() ?? 1,
                'authorized_by' => $validated['authorized_by'],
                'returned_at' => $validated['returned_at'] ?? now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $batchAccumulated = [];
            $salesToEvaluate = [];

            foreach ($validated['items'] as $itemData) {
                $qty = (int) $itemData['quantity'];
                $detailId = $itemData['sale_detail_id'];
                $shipmentId = $itemData['shipment_id'] ?? null;

                $detail = SaleDetail::with(['variant.materialModel', 'sale:id,stage,client_id'])->lockForUpdate()->findOrFail($detailId);
                $salesToEvaluate[$detail->sale_id] = $detail->sale;

                // Cálculo estricto de entregado neto
                $delivQuery = SaleDelivery::where('sale_detail_id', $detail->id)
                    ->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));

                if ($shipmentId) {
                    $delivQuery->where('shipment_id', $shipmentId);
                }
                $deliveredQty = (int) $delivQuery->sum('quantity_delivered');

                $retQuery = SaleReturnItem::where('sale_detail_id', $detail->id);
                if ($shipmentId) {
                    $retQuery->where('shipment_id', $shipmentId);
                }
                $alreadyReturned = (int) $retQuery->sum('quantity');

                $batchKey = ($shipmentId ?? 'all') . '_' . $detail->id;
                $batchUsed = $batchAccumulated[$batchKey] ?? 0;

                $availableToReturn = $deliveredQty - $alreadyReturned - $batchUsed;

                if ($qty > $availableToReturn) {
                    throw ValidationException::withMessages([
                        'items' => "La cantidad a devolver ({$qty}) excede las piezas netas entregadas disponibles ({$availableToReturn}) para la partida #{$detail->id}.",
                    ]);
                }

                $batchAccumulated[$batchKey] = $batchUsed + $qty;

                // Resolver colores según disposición
                $effectiveColorId = $itemData['color_id'] ?? $detail->color_id;
                $effectiveChosenColor = $itemData['chosen_color'] ?? $detail->chosen_color;

                if ($itemData['disposition'] === 'color_equivocado') {
                    if (!empty($itemData['color_id'])) {
                        $effectiveColorId = $itemData['color_id'];
                        $colorModel = Color::find($effectiveColorId);
                        $effectiveChosenColor = $itemData['chosen_color'] ?? ($colorModel?->name ?? 'Desconocido');
                    }
                }

                // Guardar la partida devuelta
                $returnItem = SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_detail_id' => $detail->id,
                    'shipment_id' => $shipmentId,
                    'quantity' => $qty,
                    'reason_type' => $itemData['reason_type'],
                    'disposition' => $itemData['disposition'],
                    'color_id' => $effectiveColorId,
                    'chosen_color' => $effectiveChosenColor,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Afectaciones al inventario
                $variant = $detail->variant;
                if ($variant) {
                    $variant = ProductVariant::lockForUpdate()->find($variant->id);

                    $hasColorTracking = ($variant->materialModel ? $variant->materialModel->stock_por_color : ($variant->material === 'Melamina')) 
                        || VariantColorStock::where('product_variant_id', $variant->id)->exists();

                    if ($itemData['disposition'] === 'danada') {
                        $variant->increment('stock', $qty);
                        $variant->increment('damaged_stock', $qty);

                        if ($hasColorTracking && ($effectiveColorId || $effectiveChosenColor)) {
                            $colorStock = $this->findOrCreateColorStock($variant, $effectiveColorId, $effectiveChosenColor);
                            $colorStock->increment('stock', $qty);
                            $colorStock->increment('damaged_stock', $qty);
                        }

                        DamagedStockLog::create([
                            'product_variant_id' => $variant->id,
                            'user_id' => auth()->id() ?? $saleReturn->user_id,
                            'type' => 'marcar_danada',
                            'quantity' => $qty,
                            'notes' => "Devolución {$saleReturn->folio}: " . ($itemData['notes'] ?? 'Pieza devuelta con daño'),
                        ]);
                    } elseif ($itemData['disposition'] === 'color_equivocado' || $itemData['disposition'] === 'buen_estado') {
                        $variant->increment('stock', $qty);

                        if ($hasColorTracking && ($effectiveColorId || $effectiveChosenColor)) {
                            $colorStock = $this->findOrCreateColorStock($variant, $effectiveColorId, $effectiveChosenColor);
                            $colorStock->increment('stock', $qty);
                        }
                    } elseif ($itemData['disposition'] === 'baja') {
                        // Merma total: no se suma a stock físico ni disponible
                    }
                }
            }

            // Revertir etapa de ventas que hayan quedado con saldo pendiente de entrega
            foreach ($salesToEvaluate as $sale) {
                if (!$sale) continue;
                $saleWithDetails = Sale::select(['id', 'stage'])->with(['details' => function ($q) {
                    $q->select('id', 'sale_id', 'quantity')
                    ->withSum(['deliveries as delivered_quantity' => function ($dq) {
                        $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
                    }], 'quantity_delivered')
                    ->withSum('returnItems as returned_quantity', 'quantity');
                }])->find($sale->id);

                $hasPendingItems = $saleWithDetails->details->contains(function ($d) {
                    $netDelivered = max(0, ($d->delivered_quantity ?? 0) - ($d->returned_quantity ?? 0));
                    return $netDelivered < $d->quantity;
                });

                if ($hasPendingItems && in_array($sale->stage, ['entregado', 'enviado'])) {
                    $sale->update(['stage' => 'produccion']);

                    SaleHistory::create([
                        'sale_id' => $sale->id,
                        'user_id' => auth()->id() ?? $saleReturn->user_id,
                        'to_stage' => 'produccion',
                        'notes' => "↩️ Devolución {$saleReturn->folio} registrada. Saldo pendiente de entrega por reponer.",
                    ]);
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'folio' => $saleReturn->folio,
                    'return' => $saleReturn->load('items'),
                ]);
            }

            return redirect()->route('shipments.returns.index')->with('success', "Devolución {$saleReturn->folio} registrada correctamente.");
        });
    }

    private function findOrCreateColorStock(ProductVariant $variant, ?int $colorId, ?string $colorName): VariantColorStock
    {
        $colorStock = VariantColorStock::where('product_variant_id', $variant->id)
            ->where(function ($q) use ($colorId, $colorName) {
                if ($colorId) {
                    $q->where('color_id', $colorId);
                    if ($colorName) {
                        $q->orWhere('color', $colorName);
                    }
                } else {
                    $q->where('color', $colorName);
                }
            })
            ->lockForUpdate()
            ->first();

        if (!$colorStock) {
            $colorStock = VariantColorStock::create([
                'product_variant_id' => $variant->id,
                'color' => $colorName ?? ($colorId ? Color::find($colorId)?->name : 'General'),
                'color_id' => $colorId,
                'stock' => 0,
                'damaged_stock' => 0,
                'reserved_stock' => 0,
            ]);
        } elseif (!$colorStock->color_id && $colorId) {
            $colorStock->update(['color_id' => $colorId]);
        }

        return $colorStock;
    }
}
