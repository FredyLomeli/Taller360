<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        // Cargamos productos con sus variantes y categoría
        return Inertia::render('Products/Index', [
            'products' => Product::with(['category', 'variants'])
                ->orderBy('is_favorite', 'desc') 
                ->orderBy('created_at', 'desc')
                ->get()
        ]);
    }

    public function toggleFavorite(Product $product)
    {
        $product->update(['is_favorite' => !$product->is_favorite]);
        return back();
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::all()
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validación (Ya no pedimos color en variants)
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            //'measurements' => 'nullable|string', 
            'image' => 'nullable|image|max:2048',
            'is_favorite' => 'boolean', 
            
            // Validación de Variantes (Solo Material y Precios)
            'variants' => 'required|array|min:1',
            'variants.*.material' => 'required|string',
            'variants.*.sku' => 'nullable|string',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.measurements' => 'required|string',
            'variants.*.price_1' => 'required|numeric|min:0',
            'variants.*.price_2' => 'nullable|numeric|min:0',
            'variants.*.price_3' => 'nullable|numeric|min:0',
            'variants.*.price_4' => 'nullable|numeric|min:0',
            'variants.*.price_5' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            // 2. Guardar Imagen
            $imagePath = null;
            if ($request->hasFile('image')) {
                // Usamos el disco public configurado anteriormente
                $imagePath = $request->file('image')->store('products', 'public');
            }

            // 3. Crear Producto Padre
            $product = Product::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                //'measurements' => $request->measurements,
                'image' => $imagePath,
                'is_favorite' => $request->boolean('is_favorite'),
            ]);

            // 4. Crear Variantes (Sin Color)
            foreach ($request->variants as $variantData) {
                $sku = $variantData['sku'] ?? strtoupper(substr($product->name, 0, 3) . '-' . $variantData['measurements'] . '-' . substr($variantData['material'], 0, 3));
                $sku = str_replace(' ', '', $sku); // Limpiamos espacios

                ProductVariant::create([
                    'product_id' => $product->id,
                    'material' => $variantData['material'],
                    'measurements' => $variantData['measurements'],
                    'sku' => $sku,
                    'stock' => $variantData['stock'],
                    'price_1' => $variantData['price_1'],
                    'price_2' => $variantData['price_2'] ?? null,
                    'price_3' => $variantData['price_3'] ?? null,
                    'price_4' => $variantData['price_4'] ?? null,
                    'price_5' => $variantData['price_5'] ?? null,
                ]);
            }
        });

        return redirect()->route('products.index')->with('success', 'Producto creado exitosamente.');
    }

    public function edit(Product $product)
    {
        // Cargamos el producto con sus variantes para el formulario de edición
        return Inertia::render('Products/Edit', [
            'product' => $product->load('variants'),
            'categories' => Category::all()
        ]);
    }

    public function update(Request $request, Product $product)
    {
        // Validación idéntica al store, pero a veces image es nullable si no se cambia
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            //'measurements' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_favorite' => 'boolean',

            'variants' => 'required|array|min:1',
            'variants.*.measurements' => 'required|string',
            'variants.*.material' => 'required|string',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.price_1' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $product) {
            // 1. Manejo de Imagen
            $imagePath = $product->image;
            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $imagePath = $request->file('image')->store('products', 'public');
            }

            // 2. Actualizar Padre
            $product->update([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                //'measurements' => $request->measurements,
                'image' => $imagePath,
                'is_favorite' => $request->boolean('is_favorite'),
            ]);

            // 3. Sincronización Inteligente de Variantes (Upsert)
            // Obtenemos los IDs que vienen del formulario
            $incomingIds = collect($request->variants)->pluck('id')->filter()->toArray();

            // Borramos las variantes que ya no están en el formulario (si el usuario eliminó una fila)
            // Opcional: Podrías validar si tiene ventas antes de borrar, 
            // pero el constraint de la BD (restrict) ya nos protegerá o fallará.
            $product->variants()->whereNotIn('id', $incomingIds)->delete();

            // Actualizamos o Creamos
            foreach ($request->variants as $variantData) {
                ProductVariant::updateOrCreate(
                    ['id' => $variantData['id'] ?? null], // Busca por ID
                    [
                        'product_id' => $product->id,
                        'material' => $variantData['material'],
                        'measurements' => $variantData['measurements'],
                        'sku' => $variantData['sku'] ?? null,
                        'stock' => $variantData['stock'],
                        'price_1' => $variantData['price_1'],
                        'price_2' => $variantData['price_2'] ?? null,
                        'price_3' => $variantData['price_3'] ?? null,
                        'price_4' => $variantData['price_4'] ?? null,
                        'price_5' => $variantData['price_5'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('products.index')->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        // Validamos si tiene ventas históricas antes de borrar
        // Nota: Al borrar el producto, las variantes se borran en cascada, 
        // pero la BD impedirá esto si hay ventas ligadas a las variantes.
        try {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $product->delete();
            return redirect()->route('products.index')->with('success', 'Producto eliminado.');
        } catch (\Exception $e) {
            return back()->with('error', 'No se puede eliminar este producto porque tiene ventas registradas.');
        }
    }
}