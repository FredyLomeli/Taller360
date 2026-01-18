<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
public function definition(): array
    {
        // Listas pequeñas = NO usar unique()
        $types = ['Ropero', 'Comedor', 'Silla', 'Mesa', 'Escritorio', 'Cama', 'Sofá', 'Librero'];
        $adjectives = ['Moderno', 'Clásico', 'Minimalista', 'Industrial', 'Rústico', 'Ejecutivo', 'Confort'];

        // Generamos el nombre combinando al azar
        $name = fake()->randomElement($types) . ' ' . fake()->randomElement($adjectives);

        return [
            // Importante: No usar unique() aquí tampoco
            'category_id' => Category::factory(), 
            'name' => $name, // Se pueden repetir nombres, no pasa nada
            'measurements' => fake()->numberBetween(80, 240) . 'x' . fake()->numberBetween(40, 120) . ' cm',
            'description' => fake()->text(100),
            'image' => null,
        ];
    }
}