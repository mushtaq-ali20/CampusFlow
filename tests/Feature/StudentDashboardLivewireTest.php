<?php

namespace Tests\Feature;

use App\Livewire\StudentDashboard;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentDashboardLivewireTest extends TestCase
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
    }

    public function test_dashboard_shows_active_enrollments(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create(['name' => 'VISIBLE ACTIVE COURSE']);

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 1,
        ]);

        $this->actingAs($student->user);

        $component = Livewire::test(StudentDashboard::class);

        $component->assertSee('VISIBLE ACTIVE COURSE');
    }

    public function test_dashboard_excludes_cancelled_enrollments(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create(['name' => 'CANCELLED COURSE TEST']);

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status_id' => 3,
        ]);

        $this->actingAs($student->user);

        $component = Livewire::test(StudentDashboard::class);

        $component->assertDontSee('CANCELLED COURSE TEST');
    }

    public function test_dashboard_excludes_parent_enrollments_with_children(): void
    {
        $student = Student::factory()->create();
        $parentCourse = Course::factory()->create(['name' => 'PARENT COURSE HIDDEN']);
        $childCourse = Course::factory()->create([
            'parent_course_id' => $parentCourse->id,
            'name' => 'CHILD COURSE VISIBLE',
        ]);

        $parentEnrollment = Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $parentCourse->id,
            'status_id' => 1,
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $childCourse->id,
            'status_id' => 1,
            'parent_id' => $parentEnrollment->id,
        ]);

        $this->actingAs($student->user);

        $component = Livewire::test(StudentDashboard::class);

        // Parent enrollment is excluded (it has children)
        $component->assertDontSee('PARENT COURSE HIDDEN');
        // Child enrollment is included (it has no children of its own)
        $component->assertSee('CHILD COURSE VISIBLE');
    }
}
