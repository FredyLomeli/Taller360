<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use App\Models\Category;
use App\Models\Material;
use App\Models\Color;
use App\Models\SaleDetail;
use App\Models\VariantColorStock;
use App\Models\WorkOrder;

class CatalogManagementController extends Controller
{
    /**
     * Muestra la interfaz de administración de catálogos.
     */
    public function index()
    {
        $categories = Category::with('materials')->orderBy('name')->get();

        $materials = Material::with(['colors' => function ($q) {
            $q->orderBy('material_color.orden');
        }])->orderBy('name')->get();

        // Determinar colores con historial registrado para el candado de renombrado
        $usedColorIds = collect()
            ->concat(SaleDetail::whereNotNull('color_id')->pluck('color_id'))
            ->concat(VariantColorStock::whereNotNull('color_id')->pluck('color_id'))
            ->concat(WorkOrder::whereNotNull('color_id')->pluck('color_id'))
            ->unique();

        $usedColorNames = collect()
            ->concat(SaleDetail::whereNotNull('chosen_color')->pluck('chosen_color'))
            ->concat(VariantColorStock::whereNotNull('color')->pluck('color'))
            ->concat(WorkOrder::whereNotNull('color')->pluck('color'))
            ->unique();

        $colors = Color::orderBy('name')->get()->map(function ($color) use ($usedColorIds, $usedColorNames) {
            $hasHistory = $usedColorIds->contains($color->id) || $usedColorNames->contains($color->name);
            return [
                'id' => $color->id,
                'name' => $color->name,
                'hex' => $color->hex,
                'activo' => (bool)$color->activo,
                'has_history' => $hasHistory,
            ];
        });

        return Inertia::render('Catalogs/Index', [
            'categories' => $categories,
            'materials' => $materials,
            'colors' => $colors,
        ]);
    }

    // ==========================================
    //            CATEGORÍAS
    // ==========================================

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'activo' => 'boolean',
        ]);

        Category::create([
            'name' => $validated['name'],
            'activo' => $validated['activo'] ?? true,
        ]);

        return back()->with('success', 'Categoría creada con éxito.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
            'activo' => 'boolean',
        ]);

        $category->update($validated);

        return back()->with('success', 'Categoría actualizada con éxito.');
    }

    public function syncCategoryMaterials(Request $request, Category $category)
    {
        $validated = $request->validate([
            'material_ids' => 'nullable|array',
            'material_ids.*' => 'exists:materials,id',
        ]);

        $category->materials()->sync($validated['material_ids'] ?? []);

        return back()->with('success', 'Materiales asignados a la categoría correctamente.');
    }

    // ==========================================
    //             MATERIALES
    // ==========================================

    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:materials,name',
            'color_obligatorio' => 'boolean',
            'stock_por_color' => 'boolean',
            'activo' => 'boolean',
        ]);

        Material::create([
            'name' => $validated['name'],
            'color_obligatorio' => $validated['color_obligatorio'] ?? false,
            'stock_por_color' => $validated['stock_por_color'] ?? false,
            'activo' => $validated['activo'] ?? true,
        ]);

        return back()->with('success', 'Material creado con éxito.');
    }

    public function updateMaterial(Request $request, Material $material)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:materials,name,' . $material->id,
            'color_obligatorio' => 'boolean',
            'stock_por_color' => 'boolean',
            'activo' => 'boolean',
        ]);

        $material->update($validated);

        return back()->with('success', 'Material actualizado con éxito.');
    }

    public function syncMaterialColors(Request $request, Material $material)
    {
        $validated = $request->validate([
            'color_ids' => 'nullable|array',
            'color_ids.*' => 'exists:colors,id',
        ]);

        $existing = $material->colors()->pluck('material_color.activo', 'colors.id')->all();
        $syncData = [];
        $order = 1;

        foreach ($validated['color_ids'] ?? [] as $colorId) {
            $syncData[$colorId] = [
                'activo' => $existing[$colorId] ?? true,
                'orden' => $order++,
            ];
        }

        $material->colors()->sync($syncData);

        return back()->with('success', 'Paleta de colores sincronizada con éxito.');
    }

    public function toggleMaterialColor(Request $request, Material $material, Color $color)
    {
        $validated = $request->validate([
            'activo' => 'required|boolean',
        ]);

        $material->colors()->updateExistingPivot($color->id, [
            'activo' => $validated['activo'],
        ]);

        return back()->with('success', 'Estado del color actualizado para este material.');
    }

    // ==========================================
    //               COLORES
    // ==========================================

    public function storeColor(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:colors,name',
            'hex' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => 'boolean',
        ]);

        Color::create([
            'name' => $validated['name'],
            'hex' => strtoupper($validated['hex']),
            'activo' => $validated['activo'] ?? true,
        ]);

        return back()->with('success', 'Color creado con éxito.');
    }

    public function updateColor(Request $request, Color $color)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:colors,name,' . $color->id,
            'hex' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => 'boolean',
        ]);

        // Candado de Renombrado (Regla A5)
        if ($validated['name'] !== $color->name) {
            $hasHistory = SaleDetail::where('color_id', $color->id)->orWhere('chosen_color', $color->name)->exists()
                || VariantColorStock::where('color_id', $color->id)->orWhere('color', $color->name)->exists()
                || WorkOrder::where('color_id', $color->id)->orWhere('color', $color->name)->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'name' => 'No se puede renombrar un color que ya cuenta con historial comercial o de producción registrado.',
                ]);
            }
        }

        $color->update([
            'name' => $validated['name'],
            'hex' => strtoupper($validated['hex']),
            'activo' => $validated['activo'] ?? $color->activo,
        ]);

        return back()->with('success', 'Color actualizado con éxito.');
    }
}
