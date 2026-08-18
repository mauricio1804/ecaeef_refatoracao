<?php

namespace Database\Factories;

use App\Models\EquipmentLoan;
use App\Models\Loanee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentLoan>
 */
class EquipmentLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loanee_id' => Loanee::factory(),
            'loaned_at' => now(),
            'returns_at' => now()->addDays(7),
        ];
    }
}
