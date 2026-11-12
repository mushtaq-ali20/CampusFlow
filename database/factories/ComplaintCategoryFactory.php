<?php

namespace Database\Factories;

use App\Models\ComplaintCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintCategory>
 */
class ComplaintCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'response_hours' => 24,
            'is_active' => true,
        ];
    }
}
