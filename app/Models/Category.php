<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'category_material')->withTimestamps();
    }

    public function activeMaterials()
    {
        return $this->belongsToMany(Material::class, 'category_material')
            ->where('materials.activo', true)
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }
}
