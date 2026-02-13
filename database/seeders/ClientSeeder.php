<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str; // Importante para crear el slug del email

class ClientSeeder extends Seeder
{
    public function run()
    {
        // Ruta absoluta blindada
        $path = storage_path('app/import/clientes.csv');

        if (!File::exists($path)) {
            $this->command->error("Archivo no encontrado en: " . $path);
            return;
        }

        $csvData = File::get($path);
        $lines = explode("\n", $csvData);
        
        // Saltamos encabezado
        array_shift($lines);

        foreach ($lines as $line) {
            $data = str_getcsv($line);
            
            // Validación mínima: Si no hay nombre, saltamos
            if (empty($data[0])) continue;

            $name = trim($data[0]);

            // GENERACIÓN DE EMAIL ÚNICO (Solución al error)
            // Convierte "Adan Lopez" en "adan-lopez@system.local"
            $dummyEmail = Str::slug($name) . '@system.local';

            Client::updateOrCreate(
                ['name' => $name], // Buscamos por nombre para no duplicar
                [
                    // Mapeo de campos corregido según tu contexto
                    'business_name'  => $name, // Usamos el nombre como Razón Social por defecto
                    'city'           => !empty($data[1]) ? trim($data[1]) : 'S/D',
                    'state'          => !empty($data[2]) ? trim($data[2]) : 'S/D',
                    
                    // Convertimos el teléfono a string (y si viene vacío ponemos un placeholder)
                    'phones'         => !empty($data[3]) ? trim($data[3]) : '0000000000',
                    
                    // Mapeo de lista de precios (A, B, C -> 1, 2, 3)
                    'price_tier'     => match(trim(strtoupper($data[4] ?? 'A'))) {
                        'A' => 1, 
                        'B' => 2, 
                        'C' => 3, 
                        default => 1
                    },
                    
                    // Email generado automáticamente para pasar la validación UNIQUE
                    'email'          => $dummyEmail,
                    
                    // Campos de dirección obligatorios (rellenos con genéricos)
                    'street_address' => 'Conocido',
                    'neighborhood'   => 'Centro',
                    'zip_code'       => '00000',
                    'delegation'     => 'S/D',
                    'references'     => 'Carga Masiva Inicial'
                ]
            );
        }
        $this->command->info("Clientes cargados con emails generados automáticamente.");
    }
}