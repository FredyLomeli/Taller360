<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalladoRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_detail_id',
        'quantity',
        'user_id',
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
