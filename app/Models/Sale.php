<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'stage',             // Nuevo (enum)
        'promised_date',     // Nuevo
        'is_partial_shipping' // Nuevo
    ];

    protected $casts = [
        'promised_date' => 'date',
        'is_partial_shipping' => 'boolean',
    ];

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }
    
    // Relación con el Historial (Nuevo)
    public function history()
    {
        return $this->hasMany(SaleHistory::class)->latest();
    }
    
    // Helper para saber si está liquidado
    public function getIsPaidAttribute()
    {
        return $this->paid_amount >= $this->total;
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class)->latest();
    }
}