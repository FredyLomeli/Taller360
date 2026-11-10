<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Color;
use App\Models\Material;
use App\Models\Setting;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        $onlyWithImages = Setting::getValue('catalog_only_with_images', '0');
        $filterImages = ($onlyWithImages === '1' || filter_var($onlyWithImages, FILTER_VALIDATE_BOOLEAN));

        // Obtenemos solo las categorías activas que tienen productos (respetando el filtro de imagen si aplica)
        $categories = Category::active()
            ->whereHas('products', function ($query) use ($filterImages) {
                if ($filterImages) {
                    $query->whereNotNull('image')->where('image', '!=', '');
                }
            })
            ->with([
                'activeMaterials.activeColors' => function ($query) {
                    $query->select('colors.id', 'colors.name', 'colors.hex');
                },
                'products' => function ($query) use ($filterImages) {
                    // Solo campos esenciales de los productos, NADA de precios
                    $query->select('id', 'category_id', 'name', 'description', 'image', 'is_favorite');
                    if ($filterImages) {
                        $query->whereNotNull('image')->where('image', '!=', '');
                    }
                },
                'products.variants' => function ($query) {
                    // Solo material y medidas de las variantes, NADA de precios ni stock
                    $query->select('id', 'product_id', 'material', 'material_id', 'measurements');
                }
            ])
            ->get(['id', 'name']);

        // Materiales activos y sus colores activos para construir matrices dinámicas
        $activeMaterials = Material::where('activo', true)
            ->with(['activeColors' => function ($query) {
                $query->select('colors.id', 'colors.name', 'colors.hex');
            }])
            ->get();

        $materialColors = [];
        $colorHex = [];

        foreach ($activeMaterials as $mat) {
            $matColors = $mat->activeColors->pluck('name')->values()->all();
            $materialColors[$mat->name] = $matColors;
            $materialColors[strtoupper($mat->name)] = $matColors;

            foreach ($mat->activeColors as $col) {
                $colorHex[$col->name] = $col->hex;
            }
        }

        // Colores activos globales para garantizar códigos hex
        $allActiveColors = Color::where('activo', true)->get(['name', 'hex']);
        foreach ($allActiveColors as $col) {
            if (!isset($colorHex[$col->name])) {
                $colorHex[$col->name] = $col->hex;
            }
        }

        $categoryMaterials = [];
        foreach ($categories as $cat) {
            $mats = $cat->activeMaterials->pluck('name')->values()->all();
            $categoryMaterials['category-' . $cat->id] = $mats;
            $categoryMaterials[strtolower(trim($cat->name))] = $mats;
        }

        // Obtenemos las configuraciones de la empresa
        $settingsQuery = Setting::whereIn('key', ['company_name', 'company_logo', 'company_whatsapp', 'company_phone'])->get();
        $settings = $settingsQuery->pluck('value', 'key');

        return view('catalogo.index', compact('categories', 'settings', 'categoryMaterials', 'materialColors', 'colorHex'));
    }
}
