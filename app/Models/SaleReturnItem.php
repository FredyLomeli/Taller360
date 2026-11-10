<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_return_id',
        'sale_detail_id',
        'shipment_id',
        'quantity',
        'reason_type',
        'disposition',
        'color_id',
        'chosen_color',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function colorRelation()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}
