<?php

namespace Tests\Unit;

use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_leave_type(): void
    {
        $type = LeaveType::factory()->create();

        $this->assertDatabaseHas('leave_types', ['id' => $type->id]);
    }

    public function test_name_is_translatable(): void
    {
        $type = LeaveType::factory()->create();

        $this->assertTrue(in_array('name', $type->getTranslatableAttributes()));
    }
}
