<?php

namespace Database\Factories;

use App\Models\ResultType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultType>
 */
class ResultTypeFactory extends Factory
{
    protected $model = ResultType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'description' => fake()->sentence(),
        ];
    }
}
