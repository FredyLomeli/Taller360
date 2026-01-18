<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        // Lista exacta basada en tu Imagen 1
        $categories = [
            'Roperos',
            'Trinchers',
            'Cómodas y Lokers',
            'Recámaras y comedores',
            'Bases'
        ];

        return [
            'name' => fake()->randomElement($categories),
        ];
    }
}