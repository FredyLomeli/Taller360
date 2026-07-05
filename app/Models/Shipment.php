<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'driver_name', 'license_plate', 'destination', 'status', 
        'shipped_at', 'delivered_at', 'notes', 'user_id'
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(SaleDelivery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}