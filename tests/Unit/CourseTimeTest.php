<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\CourseTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_course_time(): void
    {
        $courseTime = CourseTime::factory()->create();

        $this->assertDatabaseHas('course_times', ['id' => $courseTime->id]);
    }

    public function test_course_time_belongs_to_course(): void
    {
        $courseTime = CourseTime::factory()->create();

        $this->assertInstanceOf(Course::class, $courseTime->course);
    }

    public function test_course_times_consecutive_days_same_time_shows_range(): void
    {
        $course = Course::factory()->create();

        // Mon-Fri 18:00-20:00
        foreach (range(1, 5) as $day) {
            CourseTime::factory()->create([
                'course_id' => $course->id,
                'day' => $day,
                'start' => '18:00:00',
                'end' => '20:00:00',
            ]);
        }

        $course->refresh();
        $result = $course->course_times;

        // Should contain a range like Mon-Fri instead of listing each day
        $this->assertStringContainsString('-', $result);
        // Should NOT contain a pipe (single time slot group)
        $this->assertStringNotContainsString('|', $result);
    }

    public function test_course_times_non_consecutive_days_same_time_uses_commas(): void
    {
        $course = Course::factory()->create();

        // Mon, Wed, Fri 10:00-12:00
        foreach ([1, 3, 5] as $day) {
            CourseTime::factory()->create([
                'course_id' => $course->id,
                'day' => $day,
                'start' => '10:00:00',
                'end' => '12:00:00',
            ]);
        }

        $course->refresh();
        $result = $course->course_times;

        // Should use commas, not ranges
        $this->assertStringContainsString(',', $result);
        $this->assertStringNotContainsString('|', $result);
    }

    public function test_course_times_different_time_slots_separated_by_pipe(): void
    {
        $course = Course::factory()->create();

        // Mon 09:00-11:00
        CourseTime::factory()->create([
            'course_id' => $course->id,
            'day' => 1,
            'start' => '09:00:00',
            'end' => '11:00:00',
        ]);

        // Fri 14:00-16:00
        CourseTime::factory()->create([
            'course_id' => $course->id,
            'day' => 5,
            'start' => '14:00:00',
            'end' => '16:00:00',
        ]);

        $course->refresh();
        $result = $course->course_times;

        // Two different time slots should be separated by pipe
        $this->assertStringContainsString('|', $result);
    }

    public function test_course_times_mixed_consecutive_and_separate(): void
    {
        $course = Course::factory()->create();

        // Mon-Wed 09:00-11:00
        foreach ([1, 2, 3] as $day) {
            CourseTime::factory()->create([
                'course_id' => $course->id,
                'day' => $day,
                'start' => '09:00:00',
                'end' => '11:00:00',
            ]);
        }

        // Fri 14:00-16:00
        CourseTime::factory()->create([
            'course_id' => $course->id,
            'day' => 5,
            'start' => '14:00:00',
            'end' => '16:00:00',
        ]);

        $course->refresh();
        $result = $course->course_times;

        // Should have a range and a pipe separator
        $this->assertStringContainsString('-', $result);
        $this->assertStringContainsString('|', $result);
    }

    public function test_course_times_single_day(): void
    {
        $course = Course::factory()->create();

        CourseTime::factory()->create([
            'course_id' => $course->id,
            'day' => 2,
            'start' => '10:00:00',
            'end' => '12:00:00',
        ]);

        $course->refresh();
        $result = $course->course_times;

        // Single day, no range or pipe
        $this->assertStringNotContainsString('|', $result);
        $this->assertNotEmpty($result);
    }
}
