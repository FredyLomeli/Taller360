<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Models\SaleDetail;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity_requested' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        WorkOrder::create([
            'product_variant_id' => $request->product_variant_id,
            'quantity_requested' => $request->quantity_requested,
            'notes' => $request->notes,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Orden de trabajo creada correctamente.');
    }

    public function releaseHold(Request $request, SaleDetail $detail)
    {
        $detail->update(['production_hold' => false]);
        return back()->with('success', 'Las piezas pausadas se han reincorporado a la cola de producción.');
    }
}
