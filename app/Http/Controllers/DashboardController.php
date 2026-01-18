<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User; // Importar modelo User
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB; // Para consultas complejas

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. FILTRO DE FECHAS
        // Por defecto usamos "hoy", pero si mandan 'start_date' y 'end_date' usamos eso.
        // Si solo mandan 'date' (como en el ejemplo anterior), lo convertimos a rango de un día.
        $startDate = $request->input('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::today()->format('Y-m-d'));

        // Si usaste el filtro simple 'date' en el frontend anterior, mantenemos compatibilidad:
        if ($request->has('date')) {
            $startDate = $request->input('date');
            $endDate = $request->input('date');
        }

        // --- LÓGICA PARA ADMINISTRADOR ---
        if ($user->role === 'admin') {
            
            // A. DINERO REAL QUE ENTRÓ (Caja)
            // Sumamos 'paid_amount' de TODAS las ventas (pagadas o crédito) creadas en ese rango.
            // NOTA: Si registras abonos posteriores en otra tabla, habría que sumar esa tabla también.
            // Por ahora, basándonos en tu modelo 'Sale', 'paid_amount' es lo que entró al momento de la nota.
            $incomeTotal = Sale::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('paid_amount');

            // B. VENTAS A CRÉDITO GENERADAS (Deuda nueva)
            // Cuánto dinero quedó pendiente de cobrar en este periodo
            $creditTotal = Sale::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum(DB::raw('total - paid_amount')); // Lo que costó menos lo que pagaron

            // C. CANTIDAD DE TICKETS
            $countSales = Sale::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            // D. RENDIMIENTO POR VENDEDOR (Tabla de líderes)
            $sellersStats = User::where('role', 'vendedor')
                ->withSum(['sales as total_sold' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                }], 'paid_amount') // Sumamos lo que realmente cobraron (paid_amount)
                ->withCount(['sales as tickets_count' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                }])
                ->get();
            
            // E. PRODUCTOS CON STOCK BAJO (Global)
            $lowStockProducts = ProductVariant::with('product')
                ->where('stock', '<', 5)
                ->orderBy('stock', 'asc')
                ->take(5)
                ->get();

            return Inertia::render('Dashboard', [
                'isAdmin' => true,
                'kpis' => [
                    'income' => $incomeTotal,      // Dinero en mano (Caja)
                    'credit_receivable' => $creditTotal, // Crédito otorgado
                    'tickets' => $countSales
                ],
                'sellersStats' => $sellersStats,
                'lowStockProducts' => $lowStockProducts,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);
        }

        // --- LÓGICA PARA VENDEDOR ---
        else {
            // El vendedor solo ve SU propio rendimiento
            
            // A. SU DINERO INGRESADO
            $myIncome = Sale::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('paid_amount');

            // B. SUS TICKETS
            $myTickets = Sale::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            // C. SUS ÚLTIMAS VENTAS (Historial rápido)
            $recentSales = Sale::where('user_id', $user->id)
                ->with('client') // Asumiendo relación con cliente
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
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);
        }
    }
}