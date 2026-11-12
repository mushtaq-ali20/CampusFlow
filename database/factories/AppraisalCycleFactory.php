<?php

namespace Database\Factories;

use App\Models\AppraisalCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppraisalCycle>
 */
class AppraisalCycleFactory extends Factory
{
    /**
     * Define the model's default state: an open cycle for the year 2026.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Annual '.fake()->unique()->numberBetween(2000, 9999),
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
            'is_closed' => false,
        ];
    }
}
