<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \DB::table('enrollment_status_types')->insert([
            ['id' => 1, 'name' => json_encode(['fr' => 'Pending'])],
            ['id' => 2, 'name' => json_encode(['fr' => 'Paid'])],
            ['id' => 3, 'name' => json_encode(['fr' => 'Cancelled'])],
        ]);

        \DB::table('attendance_types')->insert([
            ['id' => 1, 'name' => json_encode(['fr' => 'Present'])],
            ['id' => 2, 'name' => json_encode(['fr' => 'Late'])],
            ['id' => 3, 'name' => json_encode(['fr' => 'Absent - Justified'])],
            ['id' => 4, 'name' => json_encode(['fr' => 'Absent - Unjustified'])],
        ]);
    }

    public function test_enrollment_creation_fires_event_and_backfills_attendance(): void
    {
        $course = Course::factory()->create();

        $pastEvent = Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->subDays(5)->toDateTimeString(),
            'end' => now()->subDays(5)->addHour()->toDateTimeString(),
        ]);

        $futureEvent = Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->addDays(5)->toDateTimeString(),
            'end' => now()->addDays(5)->addHour()->toDateTimeString(),
        ]);

        $student = Student::factory()->create();

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        // Past attendance should be backfilled as absent (type 3)
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'event_id' => $pastEvent->id,
            'attendance_type_id' => 3,
        ]);

        // Future events should NOT have attendance yet
        $this->assertDatabaseMissing('attendances', [
            'student_id' => $student->id,
            'event_id' => $futureEvent->id,
        ]);
    }

    public function test_enrollment_course_change_creates_children_for_subcourses(): void
    {
        $originalCourse = Course::factory()->create();
        $newParentCourse = Course::factory()->create();
        $childCourse = Course::factory()->create([
            'parent_course_id' => $newParentCourse->id,
        ]);

        $student = Student::factory()->create();

        // Create enrollment on the original course
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $originalCourse->id,
            'status_id' => 1,
        ]);

        // Change to a course that has children — triggers UpdateChildrenEnrollments
        // Unset cached relationship so the listener sees the new course
        $enrollment->course_id = $newParentCourse->id;
        $enrollment->unsetRelation('course');
        $enrollment->save();

        $childEnrollment = Enrollment::where('parent_id', $enrollment->id)
            ->where('course_id', $childCourse->id)
            ->first();

        $this->assertNotNull($childEnrollment);
        $this->assertEquals($student->id, $childEnrollment->student_id);
    }

    public function test_enrollment_status_cascades_to_children(): void
    {
        $originalCourse = Course::factory()->create();
        $newParentCourse = Course::factory()->create();
        Course::factory()->create([
            'parent_course_id' => $newParentCourse->id,
        ]);

        $student = Student::factory()->create();

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $originalCourse->id,
            'status_id' => 1,
        ]);

        // Change course to trigger child enrollment creation
        $enrollment->course_id = $newParentCourse->id;
        $enrollment->unsetRelation('course');
        $enrollment->save();

        // Mark parent as paid
        $enrollment->markAsPaid();

        $childEnrollment = Enrollment::where('parent_id', $enrollment->id)->first();
        $this->assertNotNull($childEnrollment);
        $this->assertEquals(2, $childEnrollment->status_id);
    }

    public function test_enrollment_mark_as_unpaid_cascades(): void
    {
        $originalCourse = Course::factory()->create();
        $newParentCourse = Course::factory()->create();
        Course::factory()->create([
            'parent_course_id' => $newParentCourse->id,
        ]);

        $student = Student::factory()->create();

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $originalCourse->id,
            'status_id' => 2,
        ]);

        $enrollment->course_id = $newParentCourse->id;
        $enrollment->unsetRelation('course');
        $enrollment->save();

        $enrollment->markAsUnpaid();

        $this->assertEquals(1, $enrollment->fresh()->status_id);

        $childEnrollment = Enrollment::where('parent_id', $enrollment->id)->first();
        $this->assertNotNull($childEnrollment);
        $this->assertEquals(1, $childEnrollment->status_id);
    }

    public function test_enrollment_cancel_deletes_children_and_attendance(): void
    {
        $course = Course::factory()->create();

        $event = Event::factory()->create([
            'course_id' => $course->id,
            'start' => now()->subDays(1)->toDateTimeString(),
            'end' => now()->subDays(1)->addHour()->toDateTimeString(),
        ]);

        $student = Student::factory()->create();

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        // Past attendance was backfilled
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'event_id' => $event->id,
        ]);

        $enrollmentId = $enrollment->id;
        $enrollment->cancel();

        $this->assertDatabaseMissing('enrollments', ['id' => $enrollmentId]);
        $this->assertDatabaseMissing('attendances', [
            'student_id' => $student->id,
            'event_id' => $event->id,
        ]);
    }

    public function test_real_enrollments_scope_returns_leaf_active_enrollments(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        // A standalone enrollment with active status — should be "real"
        $activeEnrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        // A cancelled enrollment — should NOT be "real"
        $cancelledEnrollment = Enrollment::create([
            'student_id' => Student::factory()->create()->id,
            'course_id' => $course->id,
            'status_id' => 3,
        ]);

        $realEnrollments = Enrollment::query()->real();

        $this->assertTrue($realEnrollments->contains('id', $activeEnrollment->id));
        $this->assertFalse($realEnrollments->contains('id', $cancelledEnrollment->id));
    }

    public function test_enrollment_price_uses_course_price_by_default(): void
    {
        $course = Course::factory()->create(['price' => 150]);
        $student = Student::factory()->create();

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        $this->assertEquals(150, $enrollment->price);
    }
}
