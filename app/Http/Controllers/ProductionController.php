<?php

namespace App\Http\Controllers;

use App\Models\ProductionCompletion;
use App\Models\Sale;
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

        // Regularización automática: Pedidos con devoluciones que hayan quedado con saldo pendiente
        $salesToRevert = Sale::whereIn('stage', ['entregado', 'enviado'])
            ->whereHas('details.returnItems')
            ->with(['details' => function ($q) {
                $q->withSum(['deliveries as delivered_quantity' => function ($dq) {
                    $dq->whereHas('shipment', fn($sq) => $sq->where('status', '!=', 'cancelado'));
                }], 'quantity_delivered')
                ->withSum('returnItems as returned_quantity', 'quantity');
            }])
            ->get();

        foreach ($salesToRevert as $saleRev) {
            $hasPending = $saleRev->details->contains(function ($d) {
                $netDeliv = max(0, ($d->delivered_quantity ?? 0) - ($d->returned_quantity ?? 0));
                return $netDeliv < $d->quantity;
            });
            if ($hasPending) {
                $saleRev->update(['stage' => 'produccion']);
                \App\Models\SaleHistory::create([
                    'sale_id' => $saleRev->id,
                    'user_id' => auth()->id() ?? 1,
                    'to_stage' => 'produccion',
                    'notes' => '↩️ Regularización por piezas devueltas pendientes de reposición.',
                ]);
            }
        }

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
            ->withSum('returnItems as returned_quantity', 'quantity')
            ->with(['variant.product', 'variant.colorStocks', 'variant.materialModel.activeColors', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->map(function ($item) {
                $netDelivered = max(0, ($item->delivered_quantity ?? 0) - ($item->returned_quantity ?? 0));
                return (object)[
                    'source_type' => 'sale_detail',
                    'source_id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                    'quantity' => $item->quantity,
                    'completed_quantity' => $item->completed_quantity ?? 0,
                    'detailed_quantity' => $item->detailed_quantity ?? 0,
                    'delivered_quantity' => $netDelivered,
                    'returned_quantity' => $item->returned_quantity ?? 0,
                    'chosen_color' => $item->chosen_color,
                    'color_id' => $item->color_id,
                    'custom_notes' => $item->custom_notes,
                    'additional_cost' => $item->additional_cost,
                    'is_special' => !empty($item->custom_notes) || str_starts_with($item->chosen_color ?? '', 'Especial:'),
                    'variant' => $item->variant,
                    'sale' => $item->sale,
                ];
            })
            ->filter(function ($item) use ($startWeek, $endWeek) {
                // Cálculo de faltantes previniendo la doble deducción incluyendo envíos directos y devoluciones
                $effectiveCompleted = max(0, ($item->completed_quantity ?? 0) - ($item->returned_quantity ?? 0));
                $faltantes = $item->quantity - max($effectiveCompleted, $item->detailed_quantity ?? 0, $item->delivered_quantity ?? 0);
                
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
            ->with(['productVariant.product', 'productVariant.colorStocks', 'productVariant.materialModel.activeColors'])
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
                    'chosen_color' => $wo->color,
                    'color_id' => $wo->color_id,
                    'notes' => $wo->notes,
                    'variant' => $wo->productVariant,
                    'sale' => null,
                ];
            });

        $items = collect($saleItems)->concat($workOrders);

        // 3. Agrupación unificada
        $grouped = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;
            $salesGroup = $group->where('source_type', 'sale_detail');
            $woGroup = $group->where('source_type', 'work_order');

            $salesNeeded = $salesGroup->sum('quantity');
            $salesCompleted = $salesGroup->sum('completed_quantity') ?? 0;
            $salesDetailed = $salesGroup->sum(fn($i) => $i->detailed_quantity ?? 0);
            $salesDelivered = $salesGroup->sum(fn($i) => $i->delivered_quantity ?? 0);
            $wipDetailed = max(0, $salesDetailed - $salesDelivered);
            $stockFisico = $variant->available_stock ?? 0;

            $isMelamina = ($variant->materialModel ? $variant->materialModel->stock_por_color : ($variant->material === 'Melamina'));
            $colorBreakdown = [];

            if ($isMelamina) {
                $colorStocksMap = $variant->colorStocks ? $variant->colorStocks->keyBy('color') : collect();
                $activeColors = ($variant->materialModel && $variant->materialModel->activeColors && $variant->materialModel->activeColors->isNotEmpty())
                    ? $variant->materialModel->activeColors->pluck('name')
                    : collect(['Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka']);
                $usedColors = $group->pluck('chosen_color')->filter()->unique();
                $stockColors = $variant->colorStocks ? $variant->colorStocks->pluck('color')->filter()->unique() : collect();
                $allColors = $activeColors->concat($usedColors)->concat($stockColors)->unique()->values();

                $pendingSales = 0;
                $pendingWO = 0;

                foreach ($allColors as $c) {
                    $salesForColor = $salesGroup->where('chosen_color', $c);
                    $salesNeededColor = $salesForColor->sum('quantity');
                    $salesDetailedColor = $salesForColor->sum(fn($i) => $i->detailed_quantity ?? 0);
                    $salesDeliveredColor = $salesForColor->sum(fn($i) => $i->delivered_quantity ?? 0);
                    $wipDetailedColor = max(0, $salesDetailedColor - $salesDeliveredColor);

                    $stockColorObj = $colorStocksMap->get($c);
                    $stockFisicoColor = $stockColorObj ? $stockColorObj->available_stock : 0;

                    $pendingSalesColor = max(0, $salesNeededColor - $stockFisicoColor - $wipDetailedColor - $salesDeliveredColor);

                    $woForColor = $woGroup->where('chosen_color', $c);
                    $woNeededColor = $woForColor->sum('quantity');
                    $woCompletedColor = $woForColor->sum('completed_quantity') ?? 0;
                    $pendingWOColor = max(0, $woNeededColor - $woCompletedColor);

                    $pendingToFabricateColor = $pendingSalesColor + $pendingWOColor;

                    $colorBreakdown[] = [
                        'color' => $c,
                        'needed' => $salesNeededColor + $woNeededColor,
                        'in_stock' => $stockFisicoColor,
                        'in_detailed' => $wipDetailedColor,
                        'delivered' => $salesDeliveredColor,
                        'pending_sales' => $pendingSalesColor,
                        'pending_wo' => $pendingWOColor,
                        'pending_to_fabricate' => $pendingToFabricateColor,
                    ];

                    $pendingSales += $pendingSalesColor;
                    $pendingWO += $pendingWOColor;
                }

                $salesNoColor = $salesGroup->filter(fn($i) => empty($i->chosen_color));
                $woNoColor = $woGroup->filter(fn($i) => empty($i->chosen_color));

                $noColorWoNeeded = $woNoColor->sum('quantity');
                $noColorWoCompleted = $woNoColor->sum('completed_quantity') ?? 0;
                $pendingWoNoColor = max(0, $noColorWoNeeded - $noColorWoCompleted);
                $pendingWO += $pendingWoNoColor;

                if ($salesNoColor->isNotEmpty() || $woNoColor->isNotEmpty()) {
                    $noColorSalesNeeded = $salesNoColor->sum('quantity');
                    $noColorDetailed = max(0, $salesNoColor->sum(fn($i) => $i->detailed_quantity ?? 0) - $salesNoColor->sum(fn($i) => $i->delivered_quantity ?? 0));
                    $noColorDelivered = $salesNoColor->sum(fn($i) => $i->delivered_quantity ?? 0);
                    $pendingSalesNoColor = max(0, $noColorSalesNeeded - $noColorDetailed - $noColorDelivered);
                    $pendingSales += $pendingSalesNoColor;

                    $pendingNoColorTotal = $pendingSalesNoColor + $pendingWoNoColor;

                    if ($pendingNoColorTotal > 0) {
                        $colorBreakdown[] = [
                            'color' => 'Sin Color',
                            'needed' => $noColorSalesNeeded + $noColorWoNeeded,
                            'in_stock' => 0,
                            'in_detailed' => $noColorDetailed,
                            'delivered' => $noColorDelivered,
                            'pending_sales' => $pendingSalesNoColor,
                            'pending_wo' => $pendingWoNoColor,
                            'pending_to_fabricate' => $pendingNoColorTotal,
                        ];
                    }
                }

                $pendingToFabricate = $pendingSales + $pendingWO;
            } else {
                $pendingSales = max(0, $salesNeeded - $stockFisico - $wipDetailed - $salesDelivered);
                $woNeeded = $woGroup->sum('quantity');
                $woCompleted = $woGroup->sum('completed_quantity') ?? 0;
                $pendingWO = max(0, $woNeeded - $woCompleted);
                $pendingToFabricate = $pendingSales + $pendingWO;
            }

            $totalNeeded = $salesNeeded + ($woGroup->sum('quantity') ?? 0);
            $totalCompleted = $salesCompleted + ($woGroup->sum('completed_quantity') ?? 0);
            $totalDelivered = $salesDelivered;

            return [
                'name' => $group->first()->product_name,
                'material' => $variant->material ?? 'Estándar',
                'measurements' => $variant->measurements ?? null,
                'total_quantity' => $totalNeeded,
                'breakdown' => $group->groupBy('chosen_color'),
                'color_breakdown' => $colorBreakdown,
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
                        'chosen_color' => $detail->chosen_color,
                    ];
                })->unique(fn($o) => $o['id'] . '-' . ($o['chosen_color'] ?? ''))->values(),
                'details' => $group->map(function ($item) use ($isMelamina, $colorBreakdown, $pendingSales) {
                    $effectiveCompleted = max(0, ($item->completed_quantity ?? 0) - ($item->returned_quantity ?? 0));
                    $rem = ($item->source_type === 'work_order')
                        ? max(0, $item->quantity - ($item->completed_quantity ?? 0))
                        : max(0, $item->quantity - max($effectiveCompleted, $item->detailed_quantity ?? 0, $item->delivered_quantity ?? 0));

                    $isCovered = false;
                    if ($item->source_type === 'sale_detail') {
                        if ($isMelamina && !empty($item->chosen_color)) {
                            $cb = collect($colorBreakdown)->firstWhere('color', $item->chosen_color);
                            $isCovered = ($cb && $cb['pending_sales'] === 0);
                        } else {
                            $isCovered = ($pendingSales === 0);
                        }
                    }

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
                        'pending_quantity' => $rem,
                        'chosen_color' => $item->chosen_color,
                        'color_id' => $item->color_id ?? null,
                        'custom_notes' => $item->custom_notes ?? null,
                        'is_special' => $item->is_special ?? false,
                        'notes' => $item->notes ?? null,
                        'is_covered_by_stock' => $isCovered,
                        'variant' => $item->variant,
                        'sale' => $item->sale ?? null,
                    ];
                })->values(),
                'total_needed' => $totalNeeded,
                'total_completed' => $totalCompleted,
                'total_detailed' => $wipDetailed,
                'total_delivered' => $totalDelivered,
                'in_stock' => $stockFisico,
                'pending_sales' => $pendingSales,
                'pending_wo' => $pendingWO,
                'pending_to_fabricate' => $pendingToFabricate,
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
            ->with(['variant.product', 'variant.colorStocks', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get();

        $allVariants = \App\Models\ProductVariant::select('id', 'product_id', 'material', 'material_id', 'measurements')
            ->with(['product:id,name', 'materialModel.activeColors:id,name,hex'])
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

                $color = $saleDetail->chosen_color;
                $colorId = $saleDetail->color_id;
                if ($color || $colorId) {
                    $hasColorTracking = ($saleDetail->variant->materialModel ? $saleDetail->variant->materialModel->stock_por_color : ($saleDetail->variant->material === 'Melamina')) 
                        || \App\Models\VariantColorStock::where('product_variant_id', $saleDetail->variant->id)->exists();
                    if ($hasColorTracking) {
                        $colorStock = \App\Models\VariantColorStock::where('product_variant_id', $saleDetail->variant->id)
                            ->where(function ($q) use ($colorId, $color) {
                                if ($colorId) {
                                    $q->where('color_id', $colorId)->orWhere('color', $color);
                                } else {
                                    $q->where('color', $color);
                                }
                            })->first();

                        if (!$colorStock) {
                            $colorStock = \App\Models\VariantColorStock::create([
                                'product_variant_id' => $saleDetail->variant->id,
                                'color' => $color ?? ($colorId ? \App\Models\Color::find($colorId)?->name : 'General'),
                                'color_id' => $colorId,
                                'stock' => 0,
                                'damaged_stock' => 0,
                                'reserved_stock' => 0,
                            ]);
                        } elseif (!$colorStock->color_id && $colorId) {
                            $colorStock->update(['color_id' => $colorId]);
                        }

                        $colorStock->increment('stock', $request->quantity);
                    }
                }

                SaleHistory::create([
                    'sale_id' => $saleDetail->sale_id,
                    'user_id' => auth()->id(),
                    'to_stage' => $saleDetail->sale->stage,
                    'notes' => "Producción: Se fabricaron {$request->quantity} piezas de {$saleDetail->product_name}"
                ]);
            } else {
                $workOrder = WorkOrder::with(['productVariant.materialModel'])->findOrFail($request->work_order_id);
                
                ProductionCompletion::create([
                    'work_order_id' => $workOrder->id,
                    'quantity_completed' => $request->quantity,
                    'user_id' => auth()->id(),
                    'completed_at' => now(),
                ]);

                $workOrder->productVariant->increment('stock', $request->quantity);

                $color = $workOrder->color;
                $colorId = $workOrder->color_id;
                if ($color || $colorId) {
                    $hasColorTracking = ($workOrder->productVariant->materialModel ? $workOrder->productVariant->materialModel->stock_por_color : ($workOrder->productVariant->material === 'Melamina')) 
                        || \App\Models\VariantColorStock::where('product_variant_id', $workOrder->productVariant->id)->exists();
                    if ($hasColorTracking) {
                        $colorStock = \App\Models\VariantColorStock::where('product_variant_id', $workOrder->productVariant->id)
                            ->where(function ($q) use ($colorId, $color) {
                                if ($colorId) {
                                    $q->where('color_id', $colorId)->orWhere('color', $color);
                                } else {
                                    $q->where('color', $color);
                                }
                            })->first();

                        if (!$colorStock) {
                            $colorStock = \App\Models\VariantColorStock::create([
                                'product_variant_id' => $workOrder->productVariant->id,
                                'color' => $color ?? ($colorId ? \App\Models\Color::find($colorId)?->name : 'General'),
                                'color_id' => $colorId,
                                'stock' => 0,
                                'damaged_stock' => 0,
                                'reserved_stock' => 0,
                            ]);
                        } elseif (!$colorStock->color_id && $colorId) {
                            $colorStock->update(['color_id' => $colorId]);
                        }

                        $colorStock->increment('stock', $request->quantity);
                    }
                }
                
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
            ->withSum(['deliveries as delivered_quantity' => function ($q) {
                $q->whereHas('shipment', function ($sq) {
                    $sq->where('status', '!=', 'cancelado');
                });
            }], 'quantity_delivered')
            ->withSum('returnItems as returned_quantity', 'quantity')
            ->with(['variant.product', 'variant.colorStocks', 'variant.materialModel.activeColors', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->map(function ($item) {
                $netDelivered = max(0, ($item->delivered_quantity ?? 0) - ($item->returned_quantity ?? 0));
                return (object)[
                    'source_type' => 'sale_detail',
                    'source_id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                    'quantity' => $item->quantity,
                    'completed_quantity' => $item->completed_quantity ?? 0,
                    'detailed_quantity' => $item->detailed_quantity ?? 0,
                    'delivered_quantity' => $netDelivered,
                    'returned_quantity' => $item->returned_quantity ?? 0,
                    'chosen_color' => $item->chosen_color,
                    'color_id' => $item->color_id,
                    'custom_notes' => $item->custom_notes,
                    'is_special' => !empty($item->custom_notes) || str_starts_with($item->chosen_color ?? '', 'Especial:'),
                    'variant' => $item->variant,
                    'sale' => $item->sale,
                ];
            });

        $workOrders = WorkOrder::whereIn('status', ['pending', 'in_progress'])
            ->withSum('productionCompletions as completed_quantity', 'quantity_completed')
            ->with(['productVariant.product', 'productVariant.colorStocks', 'productVariant.materialModel.activeColors'])
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
                    'chosen_color' => $wo->color,
                    'color_id' => $wo->color_id,
                    'notes' => $wo->notes,
                    'variant' => $wo->productVariant,
                    'sale' => null,
                ];
            });

        $items = collect($saleItems)->concat($workOrders)
            ->filter(function ($item) {
                if ($item->source_type === 'work_order') {
                    return ($item->quantity - ($item->completed_quantity ?? 0)) > 0;
                }
                return ($item->quantity - ($item->completed_quantity ?? 0) - ($item->detailed_quantity ?? 0)) > 0;
            })
            ->sortBy(function ($item) {
                return $item->sale->promised_date ?? '9999-12-31';
            });

        $grouped = $items->groupBy('product_variant_id')->map(function ($group) {
            $variant = $group->first()->variant;
            $salesGroup = $group->where('source_type', 'sale_detail');
            $woGroup = $group->where('source_type', 'work_order');

            $salesNeeded = $salesGroup->sum('quantity');
            $salesCompleted = $salesGroup->sum('completed_quantity') ?? 0;
            $salesDetailed = $salesGroup->sum(fn($i) => $i->detailed_quantity ?? 0);
            $salesDelivered = $salesGroup->sum(fn($i) => $i->delivered_quantity ?? 0);
            $wipDetailed = max(0, $salesDetailed - $salesDelivered);
            $stockFisico = $variant->available_stock ?? 0;

            $isMelamina = ($variant->materialModel ? $variant->materialModel->stock_por_color : ($variant->material === 'Melamina'));
            $colorBreakdown = [];

            if ($isMelamina) {
                $colorStocksMap = $variant->colorStocks ? $variant->colorStocks->keyBy('color') : collect();
                $activeColors = ($variant->materialModel && $variant->materialModel->activeColors && $variant->materialModel->activeColors->isNotEmpty())
                    ? $variant->materialModel->activeColors->pluck('name')
                    : collect(['Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka']);
                $usedColors = $group->pluck('chosen_color')->filter()->unique();
                $stockColors = $variant->colorStocks ? $variant->colorStocks->pluck('color')->filter()->unique() : collect();
                $allColors = $activeColors->concat($usedColors)->concat($stockColors)->unique()->values();

                $pendingSales = 0;
                $pendingWO = 0;

                foreach ($allColors as $c) {
                    $salesForColor = $salesGroup->where('chosen_color', $c);
                    $salesNeededColor = $salesForColor->sum('quantity');
                    $salesCompletedColor = $salesForColor->sum('completed_quantity') ?? 0;
                    $salesDetailedColor = $salesForColor->sum(fn($i) => $i->detailed_quantity ?? 0);
                    $salesDeliveredColor = $salesForColor->sum(fn($i) => $i->delivered_quantity ?? 0);
                    $wipDetailedColor = max(0, $salesDetailedColor - $salesDeliveredColor);

                    $stockColorObj = $colorStocksMap->get($c);
                    $stockFisicoColor = $stockColorObj ? $stockColorObj->available_stock : 0;

                    $pendingSalesColor = max(0, $salesNeededColor - $stockFisicoColor - $wipDetailedColor - $salesDeliveredColor);

                    $woForColor = $woGroup->where('chosen_color', $c);
                    $woNeededColor = $woForColor->sum('quantity');
                    $woCompletedColor = $woForColor->sum('completed_quantity') ?? 0;
                    $pendingWOColor = max(0, $woNeededColor - $woCompletedColor);

                    $pendingToFabricateColor = $pendingSalesColor + $pendingWOColor;

                    $colorBreakdown[] = [
                        'color' => $c,
                        'needed' => $salesNeededColor + $woNeededColor,
                        'in_stock' => $stockFisicoColor,
                        'in_detailed' => $wipDetailedColor,
                        'delivered' => $salesDeliveredColor,
                        'pending_sales' => $pendingSalesColor,
                        'pending_wo' => $pendingWOColor,
                        'pending_to_fabricate' => $pendingToFabricateColor,
                    ];

                    $pendingSales += $pendingSalesColor;
                    $pendingWO += $pendingWOColor;
                }

                $salesNoColor = $salesGroup->filter(fn($i) => empty($i->chosen_color));
                $woNoColor = $woGroup->filter(fn($i) => empty($i->chosen_color));

                $noColorWoNeeded = $woNoColor->sum('quantity');
                $noColorWoCompleted = $woNoColor->sum('completed_quantity') ?? 0;
                $pendingWoNoColor = max(0, $noColorWoNeeded - $noColorWoCompleted);
                $pendingWO += $pendingWoNoColor;

                if ($salesNoColor->isNotEmpty() || $woNoColor->isNotEmpty()) {
                    $noColorSalesNeeded = $salesNoColor->sum('quantity');
                    $noColorDetailed = max(0, $salesNoColor->sum(fn($i) => $i->detailed_quantity ?? 0) - $salesNoColor->sum(fn($i) => $i->delivered_quantity ?? 0));
                    $noColorDelivered = $salesNoColor->sum(fn($i) => $i->delivered_quantity ?? 0);
                    $pendingSalesNoColor = max(0, $noColorSalesNeeded - $noColorDetailed - $noColorDelivered);
                    $pendingSales += $pendingSalesNoColor;

                    $pendingNoColorTotal = $pendingSalesNoColor + $pendingWoNoColor;

                    if ($pendingNoColorTotal > 0) {
                        $colorBreakdown[] = [
                            'color' => 'Sin Color',
                            'needed' => $noColorSalesNeeded + $noColorWoNeeded,
                            'in_stock' => 0,
                            'in_detailed' => $noColorDetailed,
                            'delivered' => $noColorDelivered,
                            'pending_sales' => $pendingSalesNoColor,
                            'pending_wo' => $pendingWoNoColor,
                            'pending_to_fabricate' => $pendingNoColorTotal,
                        ];
                    }
                }

                $pendingToFabricate = $pendingSales + $pendingWO;
                // Filtrar para el reporte impreso: únicamente colores solicitados o a fabricar
                $colorBreakdown = collect($colorBreakdown)->filter(fn($cb) => $cb['needed'] > 0 || $cb['pending_to_fabricate'] > 0)->values()->all();
            } else {
                $pendingSales = max(0, $salesNeeded - $stockFisico - $wipDetailed - $salesDelivered);
                $pendingWO = max(0, $woGroup->sum('quantity') - ($woGroup->sum('completed_quantity') ?? 0));
                $pendingToFabricate = $pendingSales + $pendingWO;
            }

            $specialNotes = $group->map(function ($i) {
                if (!empty($i->custom_notes)) {
                    $prefix = !empty($i->chosen_color) ? "[$i->chosen_color] " : '';
                    return "{$prefix}Muestra: {$i->custom_notes}";
                }
                if (!empty($i->notes)) {
                    return "OT: {$i->notes}";
                }
                if (!empty($i->chosen_color) && str_starts_with($i->chosen_color, 'Especial:')) {
                    return $i->chosen_color;
                }
                return null;
            })->filter()->unique()->values()->all();

            return [
                'name' => $group->first()->product_name,
                'material' => $variant->material ?? 'Estándar',
                'measurements' => $variant->measurements ?? null,
                'total_needed' => $group->sum('quantity'),
                'in_stock' => $stockFisico,
                'total_detailed' => $wipDetailed,
                'pending_sales' => $pendingSales,
                'pending_wo' => $pendingWO,
                'pending_to_fabricate' => $pendingToFabricate,
                'color_breakdown' => $colorBreakdown,
                'special_notes' => $specialNotes,
                'details' => $group->map(function ($item) {
                    $rem = ($item->source_type === 'work_order')
                        ? max(0, $item->quantity - ($item->completed_quantity ?? 0))
                        : max(0, $item->quantity - max($item->completed_quantity ?? 0, $item->detailed_quantity ?? 0, $item->delivered_quantity ?? 0));

                    return (object)[
                        'source_type' => $item->source_type,
                        'source_id' => $item->source_id,
                        'sale_id' => $item->sale_id ?? null,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $item->product_name ?? ($item->variant->product->name ?? 'Mueble'),
                        'quantity' => $item->quantity,
                        'completed_quantity' => $item->completed_quantity ?? 0,
                        'detailed_quantity' => $item->detailed_quantity ?? 0,
                        'pending_quantity' => $rem,
                        'chosen_color' => $item->chosen_color,
                        'custom_notes' => $item->custom_notes ?? null,
                        'notes' => $item->notes ?? null,
                        'is_special' => $item->is_special ?? false,
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