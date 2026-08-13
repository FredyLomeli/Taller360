<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index()
    {
        // Obtenemos todas las categorías
        $categories = Category::all();
        $previewProducts = collect();

        foreach ($categories as $category) {
            // Buscamos 1 producto por categoría que tenga imagen, priorizando favoritos
            $product = Product::where('category_id', $category->id)
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->with(['category' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->select('id', 'category_id', 'name', 'image')
                ->orderByDesc('is_favorite')
                ->first();

            if ($product) {
                $previewProducts->push($product);
            }
        }

        // Obtenemos las configuraciones de la empresa para la landing
        $settingsQuery = Setting::whereIn('key', ['company_name', 'company_logo', 'company_whatsapp', 'company_phone'])->get();
        $settings = $settingsQuery->pluck('value', 'key');

        return view('landing.index', compact('previewProducts', 'settings'));
    }
}
