<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCriterion>
 */
class EvaluationCriterionFactory extends Factory
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
            'weight' => 1,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
