<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File; // Usamos File en lugar de Storage para mayor precisión con storage_path

class InventarioSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Iniciando carga de inventario desde /import...');

        // 1. Cargar Categorías
        $this->importarCSV('categories.csv', 'categories');
        
        // 2. Cargar Productos
        $this->importarCSV('products.csv', 'products');
        
        // 3. Cargar Variantes
        $this->importarCSV('product_variants.csv', 'product_variants');

        $this->command->info('¡El inventario de Taller 360 se ha cargado exitosamente!');
    }

    private function importarCSV($nombreArchivo, $tabla)
    {
        // 1. RUTA ACTUALIZADA APUNTANDO A LA CARPETA IMPORT
        $path = storage_path('app/import/' . $nombreArchivo);

        // Verifica que el archivo exista
        if (!File::exists($path)) {
            $this->command->error("⚠️ El archivo no se encontró en: " . $path);
            return;
        }

        $contenido = File::get($path);
        $lineas = explode("\n", trim($contenido));
        
        // Extraer las cabeceras (nombres de las columnas)
        $cabeceras = str_getcsv(array_shift($lineas));

        $registros = [];
        foreach ($lineas as $linea) {
            if (empty(trim($linea))) continue;
            
            $datos = str_getcsv($linea);
            
            // Combinar la cabecera con los datos para hacer un array asociativo
            $fila = array_combine($cabeceras, $datos);
            
            // Laravel necesita que le especifiquemos los timestamps
            $fila['created_at'] = now();
            $fila['updated_at'] = now();
            
            // Convertir strings vacíos a NULL para campos no obligatorios
            foreach ($fila as $key => $value) {
                if ($value === '') {
                    $fila[$key] = null;
                }
            }
            
            $registros[] = $fila;
        }

        // Insertar por bloques (chunks) para no saturar la base de datos
        foreach (array_chunk($registros, 50) as $bloque) {
            DB::table($tabla)->insert($bloque);
        }
        
        $this->command->line("✔️ Tabla '{$tabla}' cargada correctamente.");
    }
}