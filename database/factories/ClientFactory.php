<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Identificación
            'name' => fake()->name(),
            'business_name' => fake()->boolean(30) ? fake()->company() : null, // 30% prob. de tener empresa
            'price_tier' => fake()->numberBetween(1, 5),

            // Contacto
            'email' => fake()->unique()->safeEmail(),
            'phones' => fake()->phoneNumber(),

            // Dirección (Ajustado a tus columnas exactas)
            'street_address' => fake()->streetAddress(),
            'neighborhood' => 'Col. ' . fake()->word(), // Simulamos "Col. Centro"
            'city' => fake()->city(),
            'state' => fake()->state(),
            'delegation' => fake()->city(), // Usamos ciudad para simular municipio/delegación
            'zip_code' => fake()->postcode(),

            // Referencias (Texto simulado)
            'references' => 'Ref: ' . fake()->name() . ' (' . fake()->phoneNumber() . ')',
        ];
    }
}
