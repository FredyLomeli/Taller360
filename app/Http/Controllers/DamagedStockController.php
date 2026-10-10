<?php

namespace App\Http\Controllers;

use App\Models\DamagedStockLog;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DamagedStockController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'type' => 'required|in:marcar_danada,reparada,baja_definitiva',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $variant = ProductVariant::where('id', $request->product_variant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $request->quantity;
                $type = $request->type;

                if ($type === 'marcar_danada') {
                    if ($qty > $variant->available_stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "No se pueden marcar {$qty} piezas como dañadas. Solo hay {$variant->available_stock} disponibles."
                        ]);
                    }
                    $variant->increment('damaged_stock', $qty);
                } elseif ($type === 'reparada') {
                    if ($qty > $variant->damaged_stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "No se pueden reparar {$qty} piezas. Solo hay {$variant->damaged_stock} piezas dañadas registradas."
                        ]);
                    }
                    $variant->decrement('damaged_stock', $qty);
                } elseif ($type === 'baja_definitiva') {
                    if ($qty > $variant->damaged_stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "No se pueden dar de baja {$qty} piezas. Solo hay {$variant->damaged_stock} piezas dañadas para dar de baja."
                        ]);
                    }
                    if ($qty > $variant->stock) {
                        throw ValidationException::withMessages([
                            'quantity' => "No se pueden dar de baja {$qty} piezas porque el stock físico total es {$variant->stock}."
                        ]);
                    }
                    $variant->decrement('damaged_stock', $qty);
                    $variant->decrement('stock', $qty);
                }

                DamagedStockLog::create([
                    'product_variant_id' => $variant->id,
                    'user_id' => auth()->id(),
                    'type' => $type,
                    'quantity' => $qty,
                    'notes' => $request->notes,
                ]);
            });

            return back()->with('success', 'Movimiento de inventario registrado correctamente.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
