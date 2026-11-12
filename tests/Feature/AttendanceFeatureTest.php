<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \DB::table('enrollment_status_types')->insert([
            ['id' => 1, 'name' => json_encode(['fr' => 'Pending'])],
            ['id' => 2, 'name' => json_encode(['fr' => 'Paid'])],
        ]);

        \DB::table('attendance_types')->insert([
            ['id' => 1, 'name' => json_encode(['fr' => 'Present'])],
            ['id' => 2, 'name' => json_encode(['fr' => 'Late'])],
            ['id' => 3, 'name' => json_encode(['fr' => 'Absent - Justified'])],
            ['id' => 4, 'name' => json_encode(['fr' => 'Absent - Unjustified'])],
        ]);
    }

    public function test_attendance_can_be_recorded_for_event(): void
    {
        $course = Course::factory()->create();
        $event = Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->subHour()->toDateTimeString(),
            'end' => now()->toDateTimeString(),
        ]);

        $student = Student::factory()->create();

        $attendance = Attendance::create([
            'student_id' => $student->id,
            'event_id' => $event->id,
            'attendance_type_id' => 1,
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'event_id' => $event->id,
            'attendance_type_id' => 1,
        ]);
    }

    public function test_attendance_ratio_calculation(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        $events = [];
        for ($i = 0; $i < 4; $i++) {
            $events[] = Event::factory()->create([
                'course_id' => $course->id,
                'start' => now()->subDays($i + 1)->toDateTimeString(),
                'end' => now()->subDays($i + 1)->addHour()->toDateTimeString(),
            ]);
        }

        // Clear auto-created attendance from enrollment
        Attendance::where('student_id', $student->id)->delete();

        // 2 present, 1 late (75%), 1 absent
        Attendance::create(['student_id' => $student->id, 'event_id' => $events[0]->id, 'attendance_type_id' => 1]);
        Attendance::create(['student_id' => $student->id, 'event_id' => $events[1]->id, 'attendance_type_id' => 1]);
        Attendance::create(['student_id' => $student->id, 'event_id' => $events[2]->id, 'attendance_type_id' => 2]);
        Attendance::create(['student_id' => $student->id, 'event_id' => $events[3]->id, 'attendance_type_id' => 3]);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        // Ratio: (2 present + 1 late * 0.75) / 4 = 2.75/4 = 68.75 ≈ 69
        $this->assertEquals(69, $enrollment->attendance_ratio);
    }

    public function test_absence_count_calculation(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        $events = [];
        for ($i = 0; $i < 3; $i++) {
            $events[] = Event::factory()->create([
                'course_id' => $course->id,
                'start' => now()->subDays($i + 1)->toDateTimeString(),
                'end' => now()->subDays($i + 1)->addHour()->toDateTimeString(),
            ]);
        }

        // Clear auto-created attendance
        Attendance::where('student_id', $student->id)->delete();

        Attendance::create(['student_id' => $student->id, 'event_id' => $events[0]->id, 'attendance_type_id' => 1]);
        Attendance::create(['student_id' => $student->id, 'event_id' => $events[1]->id, 'attendance_type_id' => 3]);
        Attendance::create(['student_id' => $student->id, 'event_id' => $events[2]->id, 'attendance_type_id' => 4]);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        // Absences = type 3 + type 4 = 2
        $this->assertEquals(2, $enrollment->absence_count);
    }

    public function test_course_takes_attendance_attribute(): void
    {
        $course = Course::factory()->create([
            'exempt_attendance' => false,
        ]);

        // No events and no enrollments → does not take attendance
        $this->assertFalse($course->fresh()->loadCount('events')->takes_attendance);

        $student = Student::factory()->create();
        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->subHour()->toDateTimeString(),
            'end' => now()->toDateTimeString(),
        ]);

        // With events and enrollments → takes attendance
        $this->assertTrue($course->fresh()->loadCount('events')->takes_attendance);
    }

    public function test_exempt_course_does_not_take_attendance(): void
    {
        $course = Course::factory()->create([
            'exempt_attendance' => 1,
        ]);

        $student = Student::factory()->create();
        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->subHour()->toDateTimeString(),
            'end' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($course->fresh()->loadCount('events')->takes_attendance);
    }
}
