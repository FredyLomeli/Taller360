<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Material;
use App\Models\Color;
use App\Models\ProductVariant;
use App\Models\VariantColorStock;
use App\Models\WorkOrder;
use App\Models\SaleDetail;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. 15 Colores Oficiales (Fuente de verdad: catalogo/index.blade.php L437-442)
            $colorsData = [
                ['name' => 'Chocolate', 'hex' => '#3B2314'],
                ['name' => 'Caoba', 'hex' => '#4A1525'],
                ['name' => 'Tabaco', 'hex' => '#422A1D'],
                ['name' => 'Cherry', 'hex' => '#722F37'],
                ['name' => 'Nogal', 'hex' => '#5C3A21'],
                ['name' => 'Blanco', 'hex' => '#FFFFFF'],
                ['name' => 'Gris', 'hex' => '#9CA3AF'],
                ['name' => 'Gris Antracita', 'hex' => '#374151'],
                ['name' => 'Gris Cenizo', 'hex' => '#6B7280'],
                ['name' => 'Parota', 'hex' => '#8B5A2B'],
                ['name' => 'Tzalam', 'hex' => '#6F4E37'],
                ['name' => 'Moka', 'hex' => '#4A3728'],
                ['name' => 'Fresno Andino', 'hex' => '#D4C4A8'],
                ['name' => 'Nogal Africano', 'hex' => '#50382B'],
                ['name' => '258', 'hex' => '#A08A75'],
            ];

            $colorsMap = [];
            foreach ($colorsData as $data) {
                $color = Color::firstOrCreate(
                    ['name' => $data['name']],
                    ['hex' => $data['hex'], 'activo' => true]
                );
                $color->update(['hex' => $data['hex'], 'activo' => true]);
                $colorsMap[$data['name']] = $color;
            }

            // 2. 4 Materiales Oficiales
            $materialsData = [
                ['name' => 'MDF', 'color_obligatorio' => false, 'stock_por_color' => false],
                ['name' => 'Madera', 'color_obligatorio' => false, 'stock_por_color' => false],
                ['name' => 'Madera y MDF enchapado', 'color_obligatorio' => false, 'stock_por_color' => false],
                ['name' => 'Melamina', 'color_obligatorio' => true, 'stock_por_color' => true],
            ];

            $materialsMap = [];
            foreach ($materialsData as $data) {
                $material = Material::firstOrCreate(
                    ['name' => $data['name']],
                    [
                        'activo' => true,
                        'color_obligatorio' => $data['color_obligatorio'],
                        'stock_por_color' => $data['stock_por_color'],
                    ]
                );
                $material->update([
                    'activo' => true,
                    'color_obligatorio' => $data['color_obligatorio'],
                    'stock_por_color' => $data['stock_por_color'],
                ]);
                $materialsMap[$data['name']] = $material;
            }

            // 3. Relaciones Material ↔ Color (pivote material_color)
            $materialColors = [
                'MDF' => ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', '258'],
                'Madera' => ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
                'Madera y MDF enchapado' => ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
                'Melamina' => ['Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka'],
            ];

            foreach ($materialColors as $matName => $cNames) {
                $material = $materialsMap[$matName] ?? null;
                if (! $material) continue;

                $order = 1;
                foreach ($cNames as $cName) {
                    $color = $colorsMap[$cName] ?? null;
                    if ($color) {
                        $material->colors()->syncWithoutDetaching([
                            $color->id => ['activo' => true, 'orden' => $order++]
                        ]);
                    }
                }
            }

            // 4. Relaciones Categoría ↔ Material (pivote category_material)
            $categoryMaterials = [
                'Roperos' => ['MDF', 'Madera y MDF enchapado', 'Melamina', 'Madera'],
                'Trincheros' => ['Madera', 'Madera y MDF enchapado', 'MDF'],
                'Cómodas y Lokers' => ['MDF', 'Madera y MDF enchapado', 'Melamina', 'Madera'],
                'Recámaras y comedores' => ['MDF', 'Madera y MDF enchapado', 'Melamina'],
                'Bases' => ['MDF', 'Madera'],
            ];

            foreach ($categoryMaterials as $catName => $matNames) {
                $category = Category::where('name', $catName)
                    ->orWhere('name', 'like', "%{$catName}%")
                    ->first();

                if (! $category && str_contains($catName, 'Trincher')) {
                    $category = Category::where('name', 'like', '%Trinch%')->first();
                }

                if ($category) {
                    $category->update(['activo' => true]);
                    foreach ($matNames as $matName) {
                        $mat = $materialsMap[$matName] ?? null;
                        if ($mat) {
                            $category->materials()->syncWithoutDetaching([$mat->id]);
                        }
                    }
                }
            }

            // 5. Backfill de datos existentes (Regla Firme A11)
            $variants = ProductVariant::all();
            foreach ($variants as $variant) {
                $matName = trim($variant->material ?? '');
                $material = $materialsMap[$matName] ?? Material::where('name', $matName)->first();
                if ($material) {
                    $variant->material_id = $material->id;
                    $variant->save();
                }
            }

            $vcsItems = VariantColorStock::all();
            foreach ($vcsItems as $vcs) {
                $colName = trim($vcs->color ?? '');
                $color = $colorsMap[$colName] ?? Color::where('name', $colName)->first();
                if ($color) {
                    $vcs->color_id = $color->id;
                    $vcs->save();
                }
            }

            $workOrders = WorkOrder::whereNotNull('color')->get();
            foreach ($workOrders as $wo) {
                $colName = trim($wo->color ?? '');
                $color = $colorsMap[$colName] ?? Color::where('name', $colName)->first();
                if ($color) {
                    $wo->color_id = $color->id;
                    $wo->save();
                }
            }

            $saleDetails = SaleDetail::whereNotNull('chosen_color')->get();
            foreach ($saleDetails as $sd) {
                $colName = trim($sd->chosen_color ?? '');
                $color = $colorsMap[$colName] ?? Color::where('name', $colName)->first();
                if ($color) {
                    $sd->color_id = $color->id;
                    $sd->save();
                }
            }
        });
    }
}
