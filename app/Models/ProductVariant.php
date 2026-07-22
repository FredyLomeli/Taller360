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
        'price_1',
        'price_2',
        'price_3',
        'price_4',
        'price_5',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}