<?php

namespace Database\Factories;

use App\Models\EvaluationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationType>
 */
class EvaluationTypeFactory extends Factory
{
    protected $model = EvaluationType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}
