<?php

namespace Database\Factories;

use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Year>
 */
class YearFactory extends Factory
{
    protected $model = Year::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->year(),
        ];
    }
}
