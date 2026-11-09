<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hex',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'material_color')
            ->withPivot('activo', 'orden')
            ->withTimestamps();
    }

    public function colorStocks()
    {
        return $this->hasMany(VariantColorStock::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }
}
