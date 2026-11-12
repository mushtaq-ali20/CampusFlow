<?php

namespace Tests\Unit;

use App\Models\EnrollmentStatusType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentStatusTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_enrollment_status_type(): void
    {
        $type = EnrollmentStatusType::factory()->create();

        $this->assertDatabaseHas('enrollment_status_types', ['id' => $type->id]);
    }

    public function test_name_is_translatable(): void
    {
        $type = EnrollmentStatusType::factory()->create();

        $this->assertTrue(in_array('name', $type->getTranslatableAttributes()));
    }
}
