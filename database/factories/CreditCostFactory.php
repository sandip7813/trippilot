<?php

namespace Database\Factories;

use App\Enums\CreditService;
use App\Models\CreditCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCost>
 */
class CreditCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service' => fake()->randomElement(CreditService::cases()),
            'credits' => fake()->numberBetween(1, 20),
        ];
    }
}
