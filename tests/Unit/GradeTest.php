<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_grade(): void
    {
        $grade = Grade::factory()->create();

        $this->assertDatabaseHas('grades', ['id' => $grade->id]);
    }

    public function test_grade_belongs_to_enrollment(): void
    {
        $grade = Grade::factory()->create();

        $this->assertInstanceOf(Enrollment::class, $grade->enrollment);
    }

    public function test_grade_belongs_to_grade_type(): void
    {
        $grade = Grade::factory()->create();

        $this->assertInstanceOf(GradeType::class, $grade->gradeType);
    }
}
