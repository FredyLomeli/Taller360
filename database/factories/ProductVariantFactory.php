<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price1 = fake()->randomFloat(2, 1500, 15000); // Precio base entre 1500 y 15000

        return [
            'product_id' => Product::factory(), // Crea el padre si no existe
            'material' => fake()->randomElement(['MDF', 'Madera', 'Melamina', 'Pino', 'Roble']),
            'color' => fake()->randomElement(['Chocolate', 'Blanco', 'Gris', 'Nogal', 'Caoba', 'Negro']),
            'sku' => strtoupper(fake()->bothify('PROD-####-??')),
            'stock' => fake()->numberBetween(0, 50),
            
            // Precios escalonados lógicos
            'price_1' => $price1,
            'price_2' => $price1 * 0.95, // 5% desc
            'price_3' => $price1 * 0.90, // 10% desc
            'price_4' => $price1 * 0.85, // 15% desc
            'price_5' => $price1 * 0.80, // 20% desc
        ];
    }
}
