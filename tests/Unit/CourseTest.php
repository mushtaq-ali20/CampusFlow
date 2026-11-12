<?php

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Course;
use App\Models\Level;
use App\Models\Period;
use App\Models\Rhythm;
use App\Models\Room;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_course(): void
    {
        $course = Course::factory()->create();

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_course_belongs_to_period(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Period::class, $course->period);
    }

    public function test_course_belongs_to_level(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Level::class, $course->level);
    }

    public function test_course_belongs_to_rhythm(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Rhythm::class, $course->rhythm);
    }

    public function test_course_belongs_to_teacher(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Teacher::class, $course->teacher);
    }

    public function test_course_belongs_to_room(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Room::class, $course->room);
    }

    public function test_course_belongs_to_campus(): void
    {
        $course = Course::factory()->create();

        $this->assertInstanceOf(Campus::class, $course->campus);
    }
}
