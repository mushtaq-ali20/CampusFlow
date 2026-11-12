<?php

namespace Tests\Unit;

use App\Models\GradeTypeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTypeCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_grade_type_category(): void
    {
        $category = GradeTypeCategory::factory()->create();

        $this->assertDatabaseHas('grade_type_categories', ['id' => $category->id]);
    }

    public function test_name_is_translatable(): void
    {
        $category = GradeTypeCategory::factory()->create();

        $this->assertTrue(in_array('name', $category->getTranslatableAttributes()));
    }
}
