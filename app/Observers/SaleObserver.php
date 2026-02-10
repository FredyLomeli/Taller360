<?php

namespace App\Observers;

use App\Models\Sale;
use App\Models\SaleHistory;
use Illuminate\Support\Facades\Auth;

class SaleObserver
{
    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale): void
    {
        // Opcional: Registrar la creación inicial
        SaleHistory::create([
            'sale_id' => $sale->id,
            'user_id' => Auth::id() ?? $sale->user_id,
            'from_stage' => null,
            'to_stage' => $sale->stage,
            'notes' => 'Pedido creado',
        ]);
    }

    /**
     * Handle the Sale "updated" event.
     */
    public function updated(Sale $sale): void
    {
        // Solo registramos si cambió la columna 'stage' (etapa)
        if ($sale->isDirty('stage')) {
            SaleHistory::create([
                'sale_id' => $sale->id,
                'user_id' => Auth::id() ?? 1, // Usuario actual o Admin (fallback)
                'from_stage' => $sale->getOriginal('stage'), // Valor anterior
                'to_stage' => $sale->stage, // Valor nuevo
                'notes' => 'Cambio de estado automático',
            ]);
        }
    }

    /**
     * Handle the Sale "deleted" event.
     */
    public function deleted(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale): void
    {
        //
    }

    /**
     * Handle the Sale "force deleted" event.
     */
    public function forceDeleted(Sale $sale): void
    {
        //
    }
}
