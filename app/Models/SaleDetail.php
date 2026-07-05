<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'product_name',
        'quantity',
        'chosen_color',    // Nuevo: El color se elige al vender
        'custom_notes',    // Nuevo: Detalles extra
        'additional_cost', // Nuevo: Costo de lo extra
        'unit_price',
        'subtotal',
        'discount_percent',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function completions()
    {
        return $this->hasMany(ProductionCompletion::class);
    }

    public function deliveries()
    {
        return $this->hasMany(SaleDelivery::class);
    }
}