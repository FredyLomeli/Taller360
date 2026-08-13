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
                $query->where('stage', 'produccion')
                      ->where(function($q) use ($endWeek) {
                          $q->whereDate('promised_date', '<=', $endWeek)
                            ->orWhereNull('promised_date');
                      });
            })
            ->withSum('completions as completed_quantity', 'quantity_completed')
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->map(function ($item) {
                $item->source_type = 'sale_detail';
                $item->source_id = $item->id;
                return $item;
            });

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
                    'chosen_color' => null,
                    'variant' => $wo->productVariant,
                    'sale' => null,
                ];
            });

        $items = collect($saleItems)->concat($workOrders);

        // 3. Agrupación unificada
        $grouped = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;

            return [
                'name' => $group->first()->product_name,
                'material' => $variant->material ?? 'Estándar',
                'measurements' => $variant->measurements ?? null,
                'total_quantity' => $group->sum('quantity'),
                'breakdown' => $group->groupBy('chosen_color'),
                'orders' => $group->map(function($detail) {
                    if ($detail->source_type === 'work_order') {
                        return [
                            'id' => 'WO-' . $detail->source_id,
                            'has_date' => false,
                            'is_overdue' => false,
                            'promised_date' => null,
                            'type' => 'work_order',
                            'source_id' => $detail->source_id,
                        ];
                    }

                    $promised = $detail->sale->promised_date;
                    $isOverdue = $promised && Carbon::parse($promised)->startOfDay()->lt(Carbon::now()->startOfDay());

                    return [
                        'id' => 'Pedido-' . $detail->sale->id,
                        'has_date' => !is_null($promised),
                        'is_overdue' => $isOverdue,
                        'promised_date' => $promised,
                        'type' => 'sale',
                        'source_id' => $detail->id,
                    ];
                })->unique('id')->values(),
                'details' => $group,
                'total_needed' => $group->sum('quantity'),
                'total_completed' => $group->sum('completed_quantity') ?? 0,
                'in_stock' => $variant->stock ?? 0,
                'pending_to_fabricate' => max(0, $group->sum('quantity') - ($group->sum('completed_quantity') ?? 0)),
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
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get();

        $allVariants = \App\Models\ProductVariant::with('product:id,name')->get();

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
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->map(function ($item) {
                $item->source_type = 'sale_detail';
                $item->source_id = $item->id;
                return $item;
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
                    'chosen_color' => null,
                    'variant' => $wo->productVariant,
                    'sale' => null,
                ];
            });

        $items = collect($saleItems)->concat($workOrders)
            ->filter(function ($item) {
                return ($item->quantity - ($item->completed_quantity ?? 0)) > 0;
            })
            ->sortBy(function ($item) {
                return $item->sale->promised_date ?? '9999-12-31';
            });

        $grouped = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;
            $totalNeeded = $group->sum('quantity');
            $totalCompleted = $group->sum('completed_quantity') ?? 0;

            return [
                'name' => $group->first()->product_name,
                'material' => $variant->material ?? 'Estándar',
                'measurements' => $variant->measurements ?? null,
                'total_needed' => $totalNeeded,
                'in_stock' => $variant->stock ?? 0,
                'pending_to_fabricate' => max(0, $totalNeeded - $totalCompleted),
                'details' => $group
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