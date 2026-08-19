<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        $onlyWithImages = Setting::getValue('catalog_only_with_images', '0');
        $filterImages = ($onlyWithImages === '1' || filter_var($onlyWithImages, FILTER_VALIDATE_BOOLEAN));

        // Obtenemos solo las categorías que tienen productos (respetando el filtro de imagen si aplica)
        $categories = Category::whereHas('products', function ($query) use ($filterImages) {
                if ($filterImages) {
                    $query->whereNotNull('image')->where('image', '!=', '');
                }
            })
            ->with([
                'products' => function ($query) use ($filterImages) {
                    // Solo campos esenciales de los productos, NADA de precios
                    $query->select('id', 'category_id', 'name', 'description', 'image', 'is_favorite');
                    if ($filterImages) {
                        $query->whereNotNull('image')->where('image', '!=', '');
                    }
                },
                'products.variants' => function ($query) {
                    // Solo material y medidas de las variantes, NADA de precios ni stock
                    $query->select('id', 'product_id', 'material', 'measurements');
                }
            ])
            ->get(['id', 'name']); // De la categoría solo necesitamos el id y el nombre

        // Obtenemos las configuraciones de la empresa
        $settingsQuery = Setting::whereIn('key', ['company_name', 'company_logo', 'company_whatsapp', 'company_phone'])->get();
        $settings = $settingsQuery->pluck('value', 'key');

        return view('catalogo.index', compact('categories', 'settings'));
    }
}
