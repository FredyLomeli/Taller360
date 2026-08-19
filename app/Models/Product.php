<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        //'measurements', 
        'image',        
        'is_favorite'  
    ];

    protected $appends = [
        'image_url'
    ];

    /**
     * Accesor para obtener la URL pública normalizada de la imagen del producto.
     * Soporta rutas guardadas con o sin prefijo 'products/', así como URLs absolutas.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        $relativePath = str_starts_with($this->image, 'products/')
            ? $this->image
            : 'products/' . ltrim($this->image, '/');

        $segments = explode('/', $relativePath);
        $encodedPath = implode('/', array_map('rawurlencode', $segments));

        return asset('storage/' . $encodedPath);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}