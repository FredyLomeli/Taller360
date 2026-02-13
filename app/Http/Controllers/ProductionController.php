<?php

namespace App\Http\Controllers;

use App\Models\SaleDetail;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function index()
    {
        // 1. Buscamos TODOS los detalles de ventas que estén en "produccion"
        $items = SaleDetail::whereHas('sale', function ($query) {
                $query->where('stage', 'produccion');
            })
            ->with(['productVariant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
            ->get();

        // 2. Agrupación Inteligente (La magia)
        // Agrupamos por "Nombre del Producto + Material"
        $grouped = $items->groupBy(function ($item) {
            return $item->product_name . ' - ' . ($item->productVariant->material ?? 'Estándar');
        })->map(function ($group) {
            return [
                'name' => $group->first()->product_name,
                'material' => $group->first()->productVariant->material ?? 'Estándar',
                'total_quantity' => $group->sum('quantity'), // Total a fabricar (ej: 10)
                'breakdown' => $group->groupBy('chosen_color'), // Desglose por color
                'orders' => $group->pluck('sale.id')->unique()->values(), // IDs de pedidos involucrados
                'details' => $group // Para ver notas específicas si se requiere
            ];
        });

        return Inertia::render('Production/Index', [
            'productionQueue' => $grouped
        ]);
    }
}