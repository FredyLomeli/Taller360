<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'quantity_requested',
        'target_date',
        'status',
        'origin_sale_detail_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function originSaleDetail()
    {
        return $this->belongsTo(SaleDetail::class, 'origin_sale_detail_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productionCompletions()
    {
        return $this->hasMany(ProductionCompletion::class);
    }
}
