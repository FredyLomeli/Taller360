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
            
            // D. ALERTA DE STOCK (Solo Favoritos)
            $lowStockProducts = ProductVariant::where('stock', '<=', 5)
                ->whereHas('product', function ($query) {
                    // AQUÍ ESTÁ EL FILTRO CLAVE: Solo productos favoritos
                    $query->where('is_favorite', true);
                })
                ->with('product')
                ->orderBy('stock', 'asc')
                ->take(5)
                ->get();

            return Inertia::render('Dashboard', [
                'isAdmin' => true,
                'kpis' => [
                    'income' => $incomeTotal,
                    'credit_receivable' => $creditTotal,
                    'tickets' => $countSales,
                    // Agregamos los operativos
                    'in_production' => $inProduction, 
                    'ready_to_ship' => $readyToShip
                ],
                'sellersStats' => $sellersStats,
                'lowStockProducts' => $lowStockProducts,
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