<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'activo',
        'color_obligatorio',
        'stock_por_color',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'color_obligatorio' => 'boolean',
        'stock_por_color' => 'boolean',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_material')->withTimestamps();
    }

    public function colors()
    {
        return $this->belongsToMany(Color::class, 'material_color')
            ->withPivot('activo', 'orden')
            ->withTimestamps();
    }

    public function activeColors()
    {
        return $this->belongsToMany(Color::class, 'material_color')
            ->wherePivot('activo', true)
            ->where('colors.activo', true)
            ->withPivot('activo', 'orden')
            ->withTimestamps();
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
