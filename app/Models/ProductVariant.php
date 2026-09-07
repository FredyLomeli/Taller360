<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

protected $fillable = [
        'product_id',
        'material',
        'measurements',
        'sku',
        'stock',
        'reserved_stock',
        'min_stock',
        'price_1',
        'price_2',
        'price_3',
        'price_4',
        'price_5',
    ];

    protected $appends = ['available_stock'];

    public function getAvailableStockAttribute()
    {
        return max(0, $this->stock - $this->reserved_stock);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }
}