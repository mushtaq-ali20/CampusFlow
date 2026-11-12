<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\AttendanceType;
use App\Models\Event;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_attendance(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
    }

    public function test_attendance_belongs_to_student(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertInstanceOf(Student::class, $attendance->student);
    }

    public function test_attendance_belongs_to_event(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertInstanceOf(Event::class, $attendance->event);
    }

    public function test_attendance_belongs_to_attendance_type(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertInstanceOf(AttendanceType::class, $attendance->attendanceType);
    }
}
