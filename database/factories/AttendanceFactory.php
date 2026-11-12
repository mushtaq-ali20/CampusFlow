<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceType;
use App\Models\Event;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'event_id' => Event::factory(),
            'attendance_type_id' => AttendanceType::factory(),
        ];
    }
}
