<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalePaymentController extends Controller
{
    public function store(Request $request, Sale $sale)
    {
        // 1. Validación
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string|max:255',
            'paid_at' => 'required|date'
        ]);

        // 2. Seguridad: Calcular deuda actual
        // Recalculamos el pagado real desde la tabla de pagos + el anticipo inicial si no se migró
        // NOTA: Asumimos que 'paid_amount' en Sale es el acumulado.
        
        $deuda = $sale->total - $sale->paid_amount;

        if ($validated['amount'] > $deuda) {
            return back()->withErrors(['amount' => 'El monto excede la deuda pendiente ($' . number_format($deuda, 2) . ').']);
        }

        // 3. Transacción Atómica (Todo o nada)
        DB::transaction(function () use ($sale, $validated) {
            
            // A) Crear el registro de pago
            SalePayment::create([
                'sale_id' => $sale->id,
                'user_id' => auth()->id(),
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'],
                'paid_at' => $validated['paid_at']
            ]);

            // B) Actualizar el acumulado en la Venta
            $sale->increment('paid_amount', $validated['amount']);

            // Opcional: Si saldo es 0, podrías marcar status = entregado o finalizado
        });

        return back()->with('success', 'Abono registrado correctamente.');
    }
}