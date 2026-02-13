<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductLoadSeeder extends Seeder
{
    public function run()
    {
        // Asegúrate de que el nombre del archivo coincida (precios.csv)
        $path = storage_path('app/import/precios.csv');

        if (!File::exists($path)) {
            $this->command->error("Archivo no encontrado en: " . $path);
            return;
        }

        // Leemos el archivo ignorando líneas vacías
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // 1. Saltamos SOLO LA PRIMERA línea (Encabezados: NOMBRE DEL ARTICULO...)
        array_shift($lines); 

        // Creamos categoría general por defecto
        $defaultCat = Category::firstOrCreate(['name' => 'General']);

        $this->command->info("Procesando " . count($lines) . " productos...");

        foreach ($lines as $line) {
            // str_getcsv maneja automáticamente las comillas " del CSV
            $data = str_getcsv($line);
            
            // Validación: Si la fila está vacía o no tiene nombre, saltar
            if (empty($data[0])) continue;

            // Limpieza del nombre
            $fullName = trim(strtoupper($data[0]));
            
            // Lógica de Materiales
            $material = 'MADERA'; // Default
            $cleanName = $fullName;

            if (str_contains($fullName, 'MDF')) {
                $material = 'MDF';
                $cleanName = trim(str_replace('MDF', '', $fullName));
            } elseif (str_contains($fullName, 'MELAMINA')) {
                $material = 'MELAMINA';
                $cleanName = trim(str_replace('MELAMINA', '', $fullName));
            }

            // 1. Crear o Buscar el Producto (Padre)
            $product = Product::firstOrCreate(
                ['name' => $cleanName],
                [
                    'category_id' => $defaultCat->id,
                    'is_favorite' => false,
                    'description' => 'Importación automática',
                    // Si tienes campo de medidas, podrías intentar extraerlo aquí, 
                    // por ahora lo dejamos genérico o vacío.
                ]
            );

            // 2. Crear o Actualizar la Variante (Hijo)
            // IMPORTANTE: Los índices ahora son 1, 2 y 3 (A, B, C)
            ProductVariant::updateOrCreate(
                [
                    'product_id' => $product->id, 
                    'material'   => $material 
                ],
                [
                    'price_1' => $this->cleanPrice($data[1] ?? 0), // LISTA A
                    'price_2' => $this->cleanPrice($data[2] ?? 0), // LISTA B
                    'price_3' => $this->cleanPrice($data[3] ?? 0), // LISTA C
                    'stock'   => 0,
                    'sku'     => strtoupper(substr($cleanName, 0, 3)) . '-' . rand(100,999) // Generar un SKU simple si no existe
                ]
            );
        }

        $this->command->info("¡Precios actualizados correctamente!");
    }

    /**
     * Limpia el precio quitando "$", "," y espacios.
     */
    private function cleanPrice($value) {
        if (is_null($value) || $value === '') return 0;
        
        // 1. Quitamos todo lo que NO sea número o punto decimal
        // Esto elimina el signo $ y las comas (,)
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        
        return empty($cleaned) ? 0 : (float) $cleaned;
    }
}