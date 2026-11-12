<?php

namespace Tests\Unit;

use App\Models\Scholarship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_scholarship(): void
    {
        $scholarship = Scholarship::factory()->create();

        $this->assertDatabaseHas('scholarships', ['id' => $scholarship->id]);
    }

    public function test_scholarship_uses_soft_deletes(): void
    {
        $scholarship = Scholarship::factory()->create();
        $scholarship->delete();

        $this->assertSoftDeleted('scholarships', ['id' => $scholarship->id]);
    }
}
