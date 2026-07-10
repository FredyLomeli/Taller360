<?php

namespace App\Http\Controllers;

use App\Models\ProductionCompletion;
use App\Models\SaleDetail;
use App\Models\SaleHistory;
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

        // 1. Buscamos lo de esta semana + TODO LO ATRASADO + Los sin fecha
        $items = SaleDetail::whereHas('sale', function ($query) use ($endWeek) {
                $query->where('stage', 'produccion')
                      ->where(function($q) use ($endWeek) {
                          $q->whereDate('promised_date', '<=', $endWeek)
                            ->orWhereNull('promised_date');
                      });
            })
            ->withSum('completions as completed_quantity', 'quantity_completed')
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->sortBy(function ($item) {
                return $item->sale->promised_date ?? '9999-12-31';
            });

        // 3. Agrupación 
        $grouped = $items->groupBy(function ($item) {
            // TAMBIEN CAMBIAMOS AQUI: $item->variant->material
            return $item->product_name . ' - ' . ($item->variant->material ?? 'Estándar');
        })->map(function ($group) {
            return [
                'name' => $group->first()->product_name,
                // Y AQUI: $group->first()->variant->material
                'material' => $group->first()->variant->material ?? 'Estándar',
                'total_quantity' => $group->sum('quantity'),
                'breakdown' => $group->groupBy('chosen_color'),
                'orders' => $group->map(function($detail) {
                    $promised = $detail->sale->promised_date;
                    $isOverdue = $promised && Carbon::parse($promised)->startOfDay()->lt(Carbon::now()->startOfDay());

                    return [
                        'id' => $detail->sale->id,
                        'has_date' => !is_null($promised),
                        'is_overdue' => $isOverdue,
                        'promised_date' => $promised
                    ];
                })->unique('id')->values(),
                'details' => $group,
                'total_needed' => $group->sum('quantity'),
                'total_completed' => $group->sum('completed_quantity') ?? 0,
                'in_stock' => $group->first()->variant->stock ?? 0,
                'pending_to_fabricate' => max(0, $group->sum('quantity') - ($group->sum('completed_quantity') ?? 0)),
            ];
        })
        ->sortBy(function ($group) {
            // 0 = urgente (falta fabricar), 1 = listo para embarcar, 2 = ya resuelto/embarcado
            $priority = $group['pending_to_fabricate'] > 0 ? 0 : ($group['in_stock'] > 0 ? 1 : 2);
            $earliestDate = collect($group['orders'])->min('promised_date') ?? '9999-12-31';
            return sprintf('%d-%s', $priority, $earliestDate);
        });

        return Inertia::render('Production/Index', [
            'productionQueue' => $grouped,
            'weekRange' => [
                'start' => $startWeek->format('Y-m-d'),
                'end' => $endWeek->format('Y-m-d')
            ]
        ]);
    }

    // app/Http/Controllers/ProductionController.php

    public function storeCompletion(Request $request)
    {
        $request->validate(['sale_detail_id' => 'required|exists:sale_details,id', 'quantity' => 'required|integer|min:1']);
        
        $saleDetail = SaleDetail::with(['variant', 'sale'])->findOrFail($request->sale_detail_id);

        DB::transaction(function () use ($saleDetail, $request) {
            // 1. Registrar el avance
            ProductionCompletion::create([
                'sale_detail_id' => $saleDetail->id,
                'quantity_completed' => $request->quantity,
                'user_id' => auth()->id(),
                'completed_at' => now(),
            ]);

            // 2. Aumentar stock
            $saleDetail->variant->increment('stock', $request->quantity);

            // 3. Registrar en el historial del pedido (SaleObserver no lo hace solo, lo inyectamos aquí)
            SaleHistory::create([
                'sale_id' => $saleDetail->sale_id,
                'user_id' => auth()->id(),
                'to_stage' => $saleDetail->sale->stage,
                'notes' => "Producción: Se fabricaron {$request->quantity} piezas de {$saleDetail->product_name}"
            ]);
        });

        return back()->with('success', 'Avance registrado correctamente.');
    }

    public function printReport(Request $request)
    {
        $startWeek = $request->input('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfWeek()
            : \Carbon\Carbon::now()->startOfWeek();
            
        $endWeek = $startWeek->copy()->endOfWeek();

        $items = \App\Models\SaleDetail::whereHas('sale', function ($query) use ($endWeek) {
                $query->where('stage', 'produccion')
                      ->where(function($q) use ($endWeek) {
                          $q->whereDate('promised_date', '<=', $endWeek)
                            ->orWhereNull('promised_date');
                      });
            })
            ->withSum('completions as completed_quantity', 'quantity_completed')
            ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get()
            ->filter(function ($item) {
                return ($item->quantity - ($item->completed_quantity ?? 0)) > 0;
            })
            ->sortBy(function ($item) {
                return $item->sale->promised_date ?? '9999-12-31';
            });

        $grouped = $items->groupBy(function ($item) {
            return $item->product_name . ' - ' . ($item->variant->material ?? 'Estándar');
        })->map(function ($group) {
            $totalNeeded = $group->sum('quantity');
            $totalCompleted = $group->sum('completed_quantity') ?? 0;
            $inStock = $group->first()->variant->stock ?? 0;

            return [
                'name' => $group->first()->product_name,
                'material' => $group->first()->variant->material ?? 'Estándar',
                'total_needed' => $totalNeeded,
                'in_stock' => $inStock,
                'pending_to_fabricate' => max(0, $totalNeeded - $totalCompleted - $inStock),
                'details' => $group 
            ];
        });

        // Retornamos una vista en blanco (sin el menú de navegación lateral)
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