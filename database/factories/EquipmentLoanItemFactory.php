<?php

namespace Database\Factories;

use App\Enums\LoanItemStatus;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentLoanItem>
 */
class EquipmentLoanItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => EquipmentLoan::factory(),
            'equipment_id' => Equipment::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'status' => LoanItemStatus::BORROWED,
        ];
    }
}
