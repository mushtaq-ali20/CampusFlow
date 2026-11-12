<?php

namespace Database\Factories;

use App\Models\GradeBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeBand>
 */
class GradeBandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade' => fake()->unique()->lexify('G??'),
            'min_percentage' => fake()->unique()->numberBetween(1, 99),
        ];
    }
}
