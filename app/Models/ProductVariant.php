<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    // ESTA ES LA LISTA BLANCA DE CAMPOS PERMITIDOS
    protected $fillable = [
        'product_id', 
        'material', 
        'color', 
        'stock', 
        'sku',
        // Debes agregar estos 5 para que Laravel deje pasarlos:
        'price_1', 
        'price_2',
        'price_3',
        'price_4',
        'price_5'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
