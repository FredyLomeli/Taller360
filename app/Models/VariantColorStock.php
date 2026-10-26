<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantColorStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'color',
        'stock',
        'damaged_stock',
        'reserved_stock',
    ];

    protected $appends = ['available_stock'];

    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->reserved_stock - ($this->damaged_stock ?? 0));
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
