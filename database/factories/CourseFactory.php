<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Course;
use App\Models\Level;
use App\Models\Period;
use App\Models\Rhythm;
use App\Models\Room;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'name' => 'TEST COURSE LEVEL '.fake()->randomDigit(),
            'campus_id' => Campus::factory(),
            'rhythm_id' => Rhythm::factory(),
            'level_id' => Level::factory(),
            'volume' => 10,
            'price' => 100,
            'start_date' => now()->subDays(10)->format('Y-m-d'),
            'end_date' => now()->addDays(30)->format('Y-m-d'),
            'room_id' => Room::factory(),
            'teacher_id' => Teacher::factory(),
            'parent_course_id' => null,
            'exempt_attendance' => false,
            'period_id' => Period::factory(),
            'spots' => 10,
        ];
    }
}
