<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleDelivery extends Model
{
    protected $fillable = [
        'shipment_id', 'sale_detail_id', 'quantity_delivered'
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }
}