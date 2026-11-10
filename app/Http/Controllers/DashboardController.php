<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. CONFIGURACIÓN DE FECHAS (Más limpio con Carbon)
        // Si no mandan fechas, usamos el mes actual por defecto (más útil que solo "hoy")
        $start = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth();
        $end   = $request->input('end_date')   ? Carbon::parse($request->input('end_date'))   : Carbon::now()->endOfMonth();

        // Ajustamos horas para cubrir el día completo
        $startDate = $start->startOfDay();
        $endDate   = $end->endOfDay();

        // --- LÓGICA PARA ADMINISTRADOR ---
        if ($user->role === 'admin') {
            
            // A. DINERO (Solo ventas válidas: NO canceladas, NO borradores)
            // Filtramos ventas que NO sean 'pedido' (borrador) ni 'cancelado'
            $validSales = Sale::whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('stage', ['pedido', 'cancelado']);

            // Dinero Cobrado (Caja Real)
            $incomeTotal = (clone $validSales)->sum('paid_amount');

            // Crédito / Cuentas por Cobrar (Total - Pagado)
            $creditTotal = (clone $validSales)->sum(DB::raw('total - paid_amount'));

            // Cantidad de Tickets Válidos
            $countSales = (clone $validSales)->count();

            // B. OPERATIVIDAD (KPIs de Taller - ¡CRUCIAL PARA V2!)
            // Estos son totales GLOBALES (sin importar fecha) porque son tareas pendientes actuales
            $inProduction = Sale::where('stage', 'produccion')->count();
            $readyToShip  = Sale::where('stage', 'enviado')->count(); // Listos para entregar/cobrar

            // C. RENDIMIENTO POR VENDEDOR
            $sellersStats = User::where('role', 'vendedor')
                ->withSum(['sales as total_sold' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                          ->whereNotIn('stage', ['pedido', 'cancelado']);
                }], 'paid_amount')
                ->withCount(['sales as tickets_count' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                          ->whereNotIn('stage', ['pedido', 'cancelado']);
                }])
                ->get();
            
            // D. ALERTA DE STOCK (Exclusivo Favoritos / Destacados)
            $lowStockProducts = ProductVariant::select('id', 'product_id', 'material', 'measurements', 'stock', 'min_stock')
                ->whereHas('product', function ($q) {
                    $q->where('is_favorite', true);
                })
                ->with('product:id,name,image')
                ->orderBy('stock', 'asc')
                ->get();

            // E. ALERTA DE COLORES DESCONTINUADOS CON STOCK REMANENTE (Regla A6 - Consulta única indexada)
            $discontinuedColorStocks = \App\Models\VariantColorStock::where('variant_color_stocks.stock', '>', 0)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('product_variants')
                        ->join('material_color', 'material_color.material_id', '=', 'product_variants.material_id')
                        ->whereColumn('product_variants.id', 'variant_color_stocks.product_variant_id')
                        ->where('material_color.activo', false)
                        ->where(function ($sub) {
                            $sub->whereColumn('material_color.color_id', 'variant_color_stocks.color_id')
                                ->orWhere(function ($nameSub) {
                                    $nameSub->whereNull('variant_color_stocks.color_id')
                                        ->whereExists(function ($colEx) {
                                            $colEx->select(DB::raw(1))
                                                ->from('colors')
                                                ->whereColumn('colors.id', 'material_color.color_id')
                                                ->whereColumn('colors.name', 'variant_color_stocks.color');
                                        });
                                });
                        });
                })
                ->with([
                    'productVariant.product:id,name',
                    'productVariant.materialModel:id,name',
                    'colorRelation:id,name'
                ])
                ->get()
                ->map(function ($vcs) {
                    $colorName = $vcs->colorRelation ? $vcs->colorRelation->name : $vcs->color;
                    $productName = $vcs->productVariant->product->name ?? 'Producto';
                    $materialName = $vcs->productVariant->materialModel->name ?? ($vcs->productVariant->material ?? '');
                    return [
                        'id' => $vcs->id,
                        'product_name' => $productName,
                        'material_name' => $materialName,
                        'color' => $colorName,
                        'stock' => $vcs->stock,
                        'available_stock' => $vcs->available_stock,
                        'message' => "Quedan {$vcs->stock} piezas de {$productName} ({$materialName}) en color {$colorName} (descontinuado)."
                    ];
                })->values();

            // F. DEVOLUCIONES PENDIENTES DE REPOSICIÓN
            $pendingReturns = \App\Models\SaleReturn::whereHas('items.saleDetail.sale', function ($q) {
                $q->whereIn('stage', ['confirmado', 'produccion', 'enviado']);
            })
            ->with([
                'client:id,name,phones',
                'items.saleDetail.sale:id,stage',
                'items.saleDetail.variant.product:id,name',
            ])
            ->latest('returned_at')
            ->get()
            ->map(function ($ret) {
                $saleId = $ret->items->first()?->saleDetail?->sale_id;
                $totalPieces = (int) $ret->items->sum('quantity');
                $damagedPieces = (int) $ret->items->where('disposition', 'danada')->sum('quantity');
                return [
                    'id' => $ret->id,
                    'folio' => $ret->folio,
                    'client_name' => $ret->client?->name ?? 'Cliente',
                    'sale_id' => $saleId,
                    'total_pieces' => $totalPieces,
                    'damaged_pieces' => $damagedPieces,
                    'returned_at' => $ret->returned_at?->format('d/m/Y H:i'),
                    'authorized_by' => $ret->authorized_by,
                ];
            })->values();

            return Inertia::render('Dashboard', [
                'isAdmin' => true,
                'kpis' => [
                    'income' => $incomeTotal,
                    'credit_receivable' => $creditTotal,
                    'tickets' => $countSales,
                    // Agregamos los operativos
                    'in_production' => $inProduction, 
                    'ready_to_ship' => $readyToShip,
                    'pending_returns' => $pendingReturns->count(),
                ],
                'sellersStats' => $sellersStats,
                'lowStockProducts' => $lowStockProducts,
                'discontinuedColorStocks' => $discontinuedColorStocks,
                'pendingReturns' => $pendingReturns,
                'filters' => [
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d')
                ]
            ]);
        }

        // --- LÓGICA PARA VENDEDOR ---
        elseif ($user->role === 'vendedor') {
            // A. SU DINERO (Solo lo cobrado en ventas válidas)
            $mySales = Sale::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('stage', ['pedido', 'cancelado']);

            $myIncome = (clone $mySales)->sum('paid_amount');
            $myTickets = (clone $mySales)->count();

            // B. HISTORIAL RECIENTE
            $recentSales = Sale::where('user_id', $user->id)
                ->with('client')
                ->latest()
                ->take(5)
                ->get();

            return Inertia::render('Dashboard', [
                'isAdmin' => false,
                'kpis' => [
                    'income' => $myIncome,
                    'tickets' => $myTickets
                ],
                'recentSales' => $recentSales,
                'filters' => [
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d')
                ]
            ]);
        }

        // --- ROLES SIN DASHBOARD PROPIO TODAVÍA ---
        // produccion, inventario, supervisor, financiero llegan aquí
        // hasta que la Fase 3 construya sus dashboards especializados.
        // Por ahora ven una pantalla limpia de bienvenida, sin datos financieros.
        else {
            return Inertia::render('Dashboard', [
                'isAdmin' => false,
                'isOtherRole' => true,
                'role' => $user->role,
                'kpis' => [],
                'recentSales' => [],
                'filters' => [
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d')
                ]
            ]);
        }
    }
}