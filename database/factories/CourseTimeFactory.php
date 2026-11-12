<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTime>
 */
class CourseTimeFactory extends Factory
{
    protected $model = CourseTime::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'day' => fake()->numberBetween(0, 6),
            'start' => fake()->time(),
            'end' => fake()->time(),
        ];
    }
}
