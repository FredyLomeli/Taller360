<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Client;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Esto crea un Usuario y un Cliente automáticamente si no se los pasas
            'user_id' => User::factory(), 
            'client_id' => Client::factory(),
            
            // Datos de relleno para que la BD no se queje
            'total' => $this->faker->randomFloat(2, 100, 5000), // Precio entre 100 y 5000
            'paid_amount' => $this->faker->randomFloat(2, 100, 5000),
            'change_amount' => 0,
            'payment_method' => 'Efectivo',
            'status' => 'pagado',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
