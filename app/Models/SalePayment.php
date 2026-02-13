<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id', 
        'user_id', 
        'amount', 
        'payment_method', 
        'reference', 
        'paid_at'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2'
    ];

    // Relación inversa: Un pago pertenece a una venta
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    // Un pago fue registrado por un usuario
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}