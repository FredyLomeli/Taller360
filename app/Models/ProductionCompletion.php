<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_detail_id',
        'quantity_completed',
        'user_id',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}