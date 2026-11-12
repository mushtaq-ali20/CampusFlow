<?php

namespace Database\Factories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'value' => fake()->randomFloat(2, 0, 30),
            'default' => fake()->boolean(20),
        ];
    }
}
