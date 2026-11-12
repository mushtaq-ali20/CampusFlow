<?php

namespace Database\Factories;

use App\Models\GradeType;
use App\Models\GradeTypeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeType>
 */
class GradeTypeFactory extends Factory
{
    protected $model = GradeType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'total' => fake()->numberBetween(10, 100),
            'grade_type_category_id' => GradeTypeCategory::factory(),
        ];
    }
}
