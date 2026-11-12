<?php

namespace Tests\Unit;

use App\Models\AttendanceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_attendance_type(): void
    {
        $attendanceType = AttendanceType::factory()->create();

        $this->assertDatabaseHas('attendance_types', ['id' => $attendanceType->id]);
    }

    public function test_name_is_translatable(): void
    {
        $attendanceType = AttendanceType::factory()->create();

        $this->assertTrue(in_array('name', $attendanceType->getTranslatableAttributes()));
    }
}
