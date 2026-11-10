<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Models\SaleDetail;
use App\Models\ProductVariant;
use App\Models\Color;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity_requested' => 'required|integer|min:1',
            'color' => 'nullable|string|max:100',
            'color_id' => 'nullable|exists:colors,id',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $variant = ProductVariant::with(['materialModel.colors'])->findOrFail($request->product_variant_id);

        $isColorRequired = $variant->materialModel ? $variant->materialModel->color_obligatorio : ($variant->material === 'Melamina');
        
        $colorId = $request->color_id;
        $colorName = $request->color;

        // Si viene color_id, obtener su nombre
        if ($colorId) {
            $colorModel = Color::find($colorId);
            if ($colorModel) {
                $colorName = $colorModel->name;
            }
        } elseif ($colorName) {
            // Si viene nombre pero no color_id, intentar resolver color_id
            $colorModel = Color::where('name', $colorName)->first();
            if ($colorModel) {
                $colorId = $colorModel->id;
            }
        }

        // Regla A3: Si el material exige color obligatorio (ej. Melamina), no permitir sin color
        if ($isColorRequired && empty($colorName) && empty($colorId)) {
            $matName = $variant->materialModel ? $variant->materialModel->name : ($variant->material ?? 'este material');
            return back()->withErrors(['color' => "El color es obligatorio para productos de {$matName}."]);
        }

        // Regla A6: Bloquear creación de órdenes de trabajo para colores descontinuados en este material
        if ($colorId && $variant->materialModel) {
            $pivot = $variant->materialModel->colors()->where('colors.id', $colorId)->first()?->pivot;
            if ($pivot && !$pivot->activo) {
                return back()->withErrors(['color_id' => "El color '{$colorName}' está descontinuado para este material y no se pueden fabricar nuevas órdenes."]);
            }
        }

        WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity_requested' => $request->quantity_requested,
            'color' => $colorName,
            'color_id' => $colorId,
            'target_date' => $request->target_date,
            'notes' => $request->notes,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Orden de trabajo creada correctamente.');
    }

    public function releaseHold(Request $request, SaleDetail $detail)
    {
        $request->validate([
            'new_date' => 'required|date'
        ]);

        $detail->update(['production_hold' => false]);
        $detail->sale->update(['promised_date' => $request->new_date]);
        
        return back()->with('success', 'Las piezas pausadas se han reincorporado a la cola de producción.');
    }
}
