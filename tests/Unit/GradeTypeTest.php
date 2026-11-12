<?php

namespace Tests\Unit;

use App\Models\GradeType;
use App\Models\GradeTypeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_grade_type(): void
    {
        $gradeType = GradeType::factory()->create();

        $this->assertDatabaseHas('grade_types', ['id' => $gradeType->id]);
    }

    public function test_grade_type_belongs_to_category(): void
    {
        $gradeType = GradeType::factory()->create();

        $this->assertInstanceOf(GradeTypeCategory::class, $gradeType->category);
    }
}
