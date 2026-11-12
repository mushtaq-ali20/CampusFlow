<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_enrollment_belongs_to_student(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->assertInstanceOf(Student::class, $enrollment->student);
    }

    public function test_enrollment_belongs_to_course(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->assertInstanceOf(Course::class, $enrollment->course);
    }
}
