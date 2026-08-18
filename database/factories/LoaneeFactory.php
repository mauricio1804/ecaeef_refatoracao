<?php

namespace Database\Factories;

use App\Models\Loanee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loanee>
 */
class LoaneeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'document_number' => fake()->unique()->numerify('###.###.###-##'),
            'contact' => fake()->phoneNumber(),
        ];
    }
}
