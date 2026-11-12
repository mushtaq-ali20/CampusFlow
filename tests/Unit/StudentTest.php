<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_student(): void
    {
        $student = Student::factory()->create();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_student_belongs_to_user(): void
    {
        $student = Student::factory()->create();

        $this->assertInstanceOf(User::class, $student->user);
    }
}
