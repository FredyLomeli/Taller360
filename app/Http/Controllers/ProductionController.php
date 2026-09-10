<?php

namespace App\Http\Controllers;

use App\Models\ProductionCompletion;
use App\Models\SaleDetail;
use App\Models\SaleHistory;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $startWeek = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfWeek()
            : Carbon::now()->startOfWeek();

        $endWeek = $startWeek->copy()->endOfWeek();

        // 1. Buscamos lo de esta semana + TODO LO ATRASADO + Los sin fecha (SOLO NO PAUSADOS)
        $saleItems = SaleDetail::where('production_hold', false)
            ->whereHas('sale', function ($query) use ($endWeek) {
                $query->whereIn('stage', ['confirmado', 'produccion', 'enviado', 'entregado'])
                      ->where(function($q) use ($endWeek) {
                          $q->whereDate('promised_date', '<=', $endWeek)
                            ->orWhereNull('promised_date');
                      });
            })
            ->withSum('completions as completed_quantity', 'quantity_completed')
            ->withSum('detalladoRecords as detailed_quantity', 'quantity')
            ->withSum(['deliveries as delivered_quantity' => function ($q) {
                $q->whereHas('shipment', function ($sq) {
                    $sq->where('status', '!=', 'cancelado');
                });
            }], 'quantity_delivered')
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->map(function ($item) {
                return (object)[
                    'source_type' => 'sale_detail',
                    'source_id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                    'quantity' => $item->quantity,
                    'completed_quantity' => $item->completed_quantity ?? 0,
                    'detailed_quantity' => $item->detailed_quantity ?? 0,
                    'delivered_quantity' => $item->delivered_quantity ?? 0,
                    'chosen_color' => $item->chosen_color,
                    'variant' => $item->variant,
                    'sale' => $item->sale,
                ];
            })
            ->filter(function ($item) use ($startWeek, $endWeek) {
                // Cálculo de faltantes previniendo la doble deducción incluyendo envíos directos
                $faltantes = $item->quantity - max($item->completed_quantity ?? 0, $item->detailed_quantity ?? 0, $item->delivered_quantity ?? 0);
                
                // Condición A: Aún faltan piezas por fabricar
                if ($faltantes > 0) {
                    return true;
                }

                // Condición B: Si faltan 0 piezas, revisamos si la fecha promesa es de esta semana
                if ($item->sale && $item->sale->promised_date) {
                    $promisedDate = \Carbon\Carbon::parse($item->sale->promised_date)->startOfDay();
                    if ($promisedDate->between($startWeek->copy()->startOfDay(), $endWeek->copy()->endOfDay())) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        // 2. ORDENES DE TRABAJO (Pendientes o en proceso)
        $workOrders = WorkOrder::whereIn('status', ['pending', 'in_progress'])
            ->withSum('productionCompletions as completed_quantity', 'quantity_completed')
            ->with(['productVariant.product'])
            ->get()
            ->map(function ($wo) {
                return (object)[
                    'source_type' => 'work_order',
                    'source_id' => $wo->id,
                    'product_variant_id' => $wo->product_variant_id,
                    'product_name' => $wo->productVariant->product->name,
                    'quantity' => $wo->quantity_requested,
                    'completed_quantity' => $wo->completed_quantity ?? 0,
                    'detailed_quantity' => 0, // No aplica para WorkOrders
                    'chosen_color' => null,
                    'variant' => $wo->productVariant,
                    'sale' => null,
                ];
            });

        $items = collect($saleItems)->concat($workOrders);

        // 3. Agrupación unificada
        $grouped = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;
            $totalNeeded = $group->sum('quantity');
            $totalCompleted = $group->sum('completed_quantity') ?? 0;
            $totalDetailed = $group->sum(fn($i) => $i->detailed_quantity ?? 0);
            $totalDelivered = $group->sum(fn($i) => $i->delivered_quantity ?? 0);
            $wipDetailed = max(0, $totalDetailed - $totalDelivered);
            $stockFisico = $variant->available_stock ?? 0;

            return [
                'name' => $group->first()->product_name,
                'material' => $variant->material ?? 'Estándar',
                'measurements' => $variant->measurements ?? null,
                'total_quantity' => $totalNeeded,
                'breakdown' => $group->groupBy('chosen_color'),
                'orders' => $group->map(function ($detail) {
                    $isOverdue = false;
                    $promised = null;
                    if ($detail->source_type === 'sale_detail' && $detail->sale) {
                        $promised = $detail->sale->promised_date;
                        $isOverdue = $promised && \Carbon\Carbon::parse($promised)->isPast();
                    }
                    return [
                        'id' => ($detail->source_type === 'work_order') ? 'WO-' . $detail->source_id : 'Pedido-' . $detail->sale->id,
                        'has_date' => !is_null($promised),
                        'is_overdue' => $isOverdue,
                        'promised_date' => $promised,
                        'type' => ($detail->source_type === 'work_order') ? 'work_order' : 'sale',
                        'source_id' => $detail->source_id,
                    ];
                })->unique('id')->values(),
                'details' => $group->map(function ($item) {
                    return (object)[
                        'source_type' => $item->source_type,
                        'source_id' => $item->source_id,
                        'sale_id' => $item->sale_id ?? null,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                        'quantity' => $item->quantity,
                        'completed_quantity' => $item->completed_quantity ?? 0,
                        'detailed_quantity' => $item->detailed_quantity ?? 0,
                        'delivered_quantity' => $item->delivered_quantity ?? 0,
                        'chosen_color' => $item->chosen_color,
                        'variant' => $item->variant,
                        'sale' => $item->sale ?? null,
                    ];
                })->values(),
                'total_needed' => $totalNeeded,
                'total_completed' => $totalCompleted,
                'total_detailed' => $wipDetailed,
                'total_delivered' => $totalDelivered,
                'in_stock' => $stockFisico,
                'pending_to_fabricate' => max(0, $totalNeeded - $stockFisico - $wipDetailed - $totalDelivered),
            ];
        })
        ->sortBy(function ($group) {
            $priority = $group['pending_to_fabricate'] > 0 ? 0 : ($group['in_stock'] > 0 ? 1 : 2);
            $earliestDate = collect($group['orders'])->min('promised_date') ?? '9999-12-31';
            return sprintf('%d-%s', $priority, $earliestDate);
        });

        $pausedItems = SaleDetail::where('production_hold', true)
            ->whereHas('sale', function ($query) {
                $query->whereIn('stage', ['produccion', 'confirmado', 'enviado']);
            })
            ->withSum('completions as completed_quantity', 'quantity_completed')
            ->withSum('detalladoRecords as detailed_quantity', 'quantity')
            ->withSum(['deliveries as delivered_quantity' => function ($q) {
                $q->whereHas('shipment', function ($sq) {
                    $sq->where('status', '!=', 'cancelado');
                });
            }], 'quantity_delivered')
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get();

        $allVariants = \App\Models\ProductVariant::select('id', 'product_id', 'material', 'measurements')
            ->with('product:id,name')
            ->get();

        return Inertia::render('Production/Index', [
            'productionQueue' => $grouped,
            'pausedItems' => $pausedItems,
            'allVariants' => $allVariants,
            'weekRange' => [
                'start' => $startWeek->format('Y-m-d'),
                'end' => $endWeek->format('Y-m-d')
            ]
        ]);
    }

    public function storeCompletion(Request $request)
    {
        $request->validate([
            'sale_detail_id' => 'nullable|exists:sale_details,id',
            'work_order_id' => 'nullable|exists:work_orders,id',
            'quantity' => 'required|integer|min:1'
        ]);

        if (!$request->sale_detail_id && !$request->work_order_id) {
            return back()->withErrors(['error' => 'Debe indicar a qué partida o orden de trabajo pertenece este avance.']);
        }

        DB::transaction(function () use ($request) {
            if ($request->sale_detail_id) {
                $saleDetail = SaleDetail::with(['variant', 'sale'])->findOrFail($request->sale_detail_id);
                
                ProductionCompletion::create([
                    'sale_detail_id' => $saleDetail->id,
                    'quantity_completed' => $request->quantity,
                    'user_id' => auth()->id(),
                    'completed_at' => now(),
                ]);

                $saleDetail->variant->increment('stock', $request->quantity);

                SaleHistory::create([
                    'sale_id' => $saleDetail->sale_id,
                    'user_id' => auth()->id(),
                    'to_stage' => $saleDetail->sale->stage,
                    'notes' => "Producción: Se fabricaron {$request->quantity} piezas de {$saleDetail->product_name}"
                ]);
            } else {
                $workOrder = WorkOrder::with('productVariant')->findOrFail($request->work_order_id);
                
                ProductionCompletion::create([
                    'work_order_id' => $workOrder->id,
                    'quantity_completed' => $request->quantity,
                    'user_id' => auth()->id(),
                    'completed_at' => now(),
                ]);

                $workOrder->productVariant->increment('stock', $request->quantity);
                
                $totalCompleted = $workOrder->productionCompletions()->sum('quantity_completed');
                if ($totalCompleted >= $workOrder->quantity_requested) {
                    $workOrder->update(['status' => 'completed']);
                } else if ($workOrder->status === 'pending') {
                    $workOrder->update(['status' => 'in_progress']);
                }
            }
        });

        return back()->with('success', 'Avance registrado correctamente.');
    }

    public function printReport(Request $request)
    {
        $startWeek = $request->input('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfWeek()
            : \Carbon\Carbon::now()->startOfWeek();

        $endWeek = $startWeek->copy()->endOfWeek();

        $saleItems = SaleDetail::where('production_hold', false)
            ->whereHas('sale', function ($query) use ($endWeek) {
                $query->where('stage', 'produccion')
                      ->where(function($q) use ($endWeek) {
                          $q->whereDate('promised_date', '<=', $endWeek)
                            ->orWhereNull('promised_date');
                      });
            })
            ->withSum('completions as completed_quantity', 'quantity_completed')
            ->withSum('detalladoRecords as detailed_quantity', 'quantity')
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->map(function ($item) {
                return (object)[
                    'source_type' => 'sale_detail',
                    'source_id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                    'quantity' => $item->quantity,
                    'completed_quantity' => $item->completed_quantity ?? 0,
                    'detailed_quantity' => $item->detailed_quantity ?? 0,
                    'chosen_color' => $item->chosen_color,
                    'variant' => $item->variant,
                    'sale' => $item->sale,
                ];
            });

        $workOrders = WorkOrder::whereIn('status', ['pending', 'in_progress'])
            ->withSum('productionCompletions as completed_quantity', 'quantity_completed')
            ->with(['productVariant.product'])
            ->get()
            ->map(function ($wo) {
                return (object)[
                    'source_type' => 'work_order',
                    'source_id' => $wo->id,
                    'product_variant_id' => $wo->product_variant_id,
                    'product_name' => $wo->productVariant->product->name,
                    'quantity' => $wo->quantity_requested,
                    'completed_quantity' => $wo->completed_quantity ?? 0,
                    'detailed_quantity' => 0,
                    'chosen_color' => null,
                    'variant' => $wo->productVariant,
                    'sale' => null,
                ];
            });

        $items = collect($saleItems)->concat($workOrders)
            ->filter(function ($item) {
                return ($item->quantity - ($item->completed_quantity ?? 0) - ($item->detailed_quantity ?? 0)) > 0;
            })
            ->sortBy(function ($item) {
                return $item->sale->promised_date ?? '9999-12-31';
            });

        $grouped = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;
              $totalNeeded = $group->sum('quantity');
              $totalCompleted = $group->sum('completed_quantity') ?? 0;
              $totalDetailed = $group->sum(fn($i) => $i->detailed_quantity ?? 0);
              $totalDelivered = $group->sum(fn($i) => $i->delivered_quantity ?? 0);
              $wipDetailed = max(0, $totalDetailed - $totalDelivered);
              $stockFisico = $variant->available_stock ?? 0;

              return [
                  'name' => $group->first()->product_name,
                  'material' => $variant->material ?? 'Estándar',
                  'measurements' => $variant->measurements ?? null,
                  'total_needed' => $totalNeeded,
                  'in_stock' => $stockFisico,
                  'total_detailed' => $wipDetailed,
                  'pending_to_fabricate' => max(0, $totalNeeded - $stockFisico - $wipDetailed - $totalDelivered),
                'details' => $group->map(function ($item) {
                    return (object)[
                        'source_type' => $item->source_type,
                        'source_id' => $item->source_id,
                        'sale_id' => $item->sale_id ?? null,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                        'quantity' => $item->quantity,
                        'completed_quantity' => $item->completed_quantity ?? 0,
                        'detailed_quantity' => $item->detailed_quantity ?? 0,
                        'chosen_color' => $item->chosen_color,
                        'variant' => $item->variant,
                        'sale' => $item->sale ?? null,
                    ];
                })->values()
            ];
        });

        return \Inertia\Inertia::render('Production/Print', [
            'productionQueue' => $grouped,
            'reportDate' => now()->format('d/m/Y H:i'),
            'weekRange' => [
                'start' => $startWeek->format('Y-m-d'),
                'end' => $endWeek->format('Y-m-d')
            ]
        ]);
    }
}