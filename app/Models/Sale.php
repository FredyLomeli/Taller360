<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id', 
        'client_id', 
        'total', 
        'paid_amount',  
        'change_amount', 
        'payment_method', 
        'status'
    ];

    // Una venta tiene muchos detalles
    public function details() {
        return $this->hasMany(SaleDetail::class);
    }
    
    // Una venta pertenece a un cliente
    public function client() {
        return $this->belongsTo(Client::class);
    }

    // Relación: Una venta pertenece a un usuario (vendedor)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
