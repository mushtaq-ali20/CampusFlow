<?php

namespace Database\Factories;

use App\Models\GradeTypeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeTypeCategory>
 */
class GradeTypeCategoryFactory extends Factory
{
    protected $model = GradeTypeCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}
