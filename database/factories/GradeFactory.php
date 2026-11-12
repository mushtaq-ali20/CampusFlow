<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'grade_type_id' => GradeType::factory(),
            'enrollment_id' => Enrollment::factory(),
            'grade' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
