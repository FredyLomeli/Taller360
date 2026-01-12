<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleDetail extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'product_name',
        'quantity',
        'discount_percent', 
        'unit_price',
        'subtotal'
    ];
}
