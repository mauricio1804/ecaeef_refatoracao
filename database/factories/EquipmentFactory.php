<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Bola futeboll', 'halter', 'Barra', 'Bola volei', 'raquete']),
            'asset_number' => fake()->unique()->numerify('PAT-#####'),
        ];
    }
}
