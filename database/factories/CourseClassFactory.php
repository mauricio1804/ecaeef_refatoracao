<?php

namespace Database\Factories;

use App\Models\CourseClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseClass>
 */
class CourseClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Sala 1', 'Sala 2', 'Laboratório A', 'Laboratório B', 'Auditório']),
        ];
    }
}
