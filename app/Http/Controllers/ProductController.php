<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Product;
use App\Models\Client;
use App\Models\Category;
use App\Models\SaleDetail;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class ProductController extends Controller
{

    public function index()
    {

        return Inertia::render('Products/Index', [
            'products' => Product::with(['category', 'variants'])->get(),
            'clients' => Client::all(),
            'categories' => Category::all() 
        ]);
    }

    public function create()
    {
        // Cargamos las categorías para el selector
        return Inertia::render('Products/Create', [
            'categories' => Category::all()
        ]);
    }

    public function store(Request $request)
    {
        // Validaciones (Agregamos la validación de imagen)
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048', // <--- Nueva regla: Imagen máx 2MB
            'variants' => 'required|array|min:1',
            // ... resto de validaciones ...
        ]);

        DB::transaction(function () use ($request) {
            
            // 1. Manejo de la Imagen
            $imagePath = null;
            if ($request->hasFile('image')) {
                // Guarda en la carpeta 'public/products' y devuelve la ruta
                $imagePath = $request->file('image')->store('products', 'public');
            }

            // 2. Crear Producto Padre
            $product = Product::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'measurements' => $request->measurements,
                'description' => $request->description,
                'image' => $imagePath, // <--- Guardamos la ruta
            ]);

            // ... (el código de variantes sigue igual) ...
            foreach ($request->variants as $variant) {
                $product->variants()->create([
                    'material' => $variant['material'],
                    'color' => $variant['color'],
                    'stock' => $variant['stock'] ?? 0,
                    'sku' => $variant['sku'] ?? null,
                    'price_1' => $variant['price_1'],
                    'price_2' => $variant['price_2'] ?? null,
                    'price_3' => $variant['price_3'] ?? null,
                    'price_4' => $variant['price_4'] ?? null,
                    'price_5' => $variant['price_5'] ?? null,
                ]);
            }
        });

        return redirect()->route('products.inventory'); // Redirigimos al inventario
    }

    public function inventory()
    {
        // Traemos los productos paginados (de 10 en 10) para que la tabla no sea kilométrica
        $products = Product::with(['category', 'variants'])->latest()->get();

        return Inertia::render('Products/Inventory', [
            'products' => $products
        ]);
    }

    public function edit($id)
    {
        $product = Product::with('variants')->findOrFail($id);
        
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => Category::all()
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        // 1. Actualizamos datos del padre
        $dataToUpdate = [
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'measurements' => $request->measurements, // Asegúrate de tener este campo en fillable
        ];

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $dataToUpdate['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($dataToUpdate);

        // 2. LOGICA DE VARIANTES CORREGIDA
        DB::transaction(function () use ($request, $product) {
            
            // A. LIMPIEZA: Identificar qué variantes se quitaron en el formulario
            // Obtenemos solo los IDs que vienen en el request (los que el usuario dejó vivos)
            $existingIds = collect($request->variants)->pluck('id')->filter()->toArray();
            
            // Borramos de la base de datos las variantes que NO vienen en el formulario
            $product->variants()->whereNotIn('id', $existingIds)->delete();

            // B. ACTUALIZACIÓN / CREACIÓN (Upsert)
            foreach ($request->variants as $variantData) {
                // updateOrCreate busca por ID. 
                // Si el ID existe y coincide con el producto, actualiza.
                // Si el ID es null o no existe, crea uno nuevo.
                $product->variants()->updateOrCreate(
                    [
                        'id' => $variantData['id'] ?? null // Clave de búsqueda
                    ], 
                    [
                        'material' => $variantData['material'],
                        'color' => $variantData['color'],
                        'stock' => $variantData['stock'] ?? 0,
                        'sku' => $variantData['sku'] ?? null,
                        'price_1' => $variantData['price_1'],
                        'price_2' => $variantData['price_2'] ?? null,
                        'price_3' => $variantData['price_3'] ?? null,
                        'price_4' => $variantData['price_4'] ?? null,
                        'price_5' => $variantData['price_5'] ?? null,
                        // Importante: Aseguramos que pertenezca al producto (para los nuevos)
                        'product_id' => $product->id 
                    ]
                );
            }
        });

        return redirect()->route('products.inventory')->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy($id)
    {
        $product = Product::with('variants')->findOrFail($id);

        // 1. REGLA DE ORO: Verificar si alguna variante ya se vendió
        // Obtenemos los IDs de las variantes de este producto
        $variantIds = $product->variants->pluck('id');

        // Buscamos si existen en la tabla de ventas
        $isUsedInSales = SaleDetail::whereIn('product_variant_id', $variantIds)->exists();

        if ($isUsedInSales) {
            // Si existe, DETENEMOS TODO y regresamos error
            return back()->withErrors([
                'error' => 'No se puede eliminar el producto "' . $product->name . '" porque forma parte de una o más ventas registradas en el historial.'
            ]);
        }

        // 2. Si no se ha vendido, procedemos al borrado seguro
        // (Aquí sí aplica el borrado en cascada: Se borra Producto y sus Variantes)
        $product->delete();

        return back()->with('success', 'Producto eliminado correctamente.');
    }

    public function destroyVariant($id)
    {
        $variant = ProductVariant::findOrFail($id);

        // 1. EL GUARDIA: Verificar si esta variante específica se vendió
        $isSold = SaleDetail::where('product_variant_id', $id)->exists();

        if ($isSold) {
            // Importante: regresar con 'error' para que el frontend lo lea
            return back()->withErrors([
                'error' => 'No puedes eliminar esta variante ("' . $variant->color . ' - ' . $variant->material . '") porque ya se ha vendido en el pasado.'
            ]);
        }

        // 2. Borrar si está limpia
        $variant->delete();

        return back()->with('success', 'Variante eliminada.');
    }
}
