<?php

namespace Tests\Unit;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_leave(): void
    {
        $leave = Leave::factory()->create();

        $this->assertDatabaseHas('leaves', ['id' => $leave->id]);
    }

    public function test_leave_belongs_to_teacher(): void
    {
        $leave = Leave::factory()->create();

        $this->assertInstanceOf(Teacher::class, $leave->teacher);
    }

    public function test_leave_belongs_to_leave_type(): void
    {
        $leave = Leave::factory()->create();

        $this->assertInstanceOf(LeaveType::class, $leave->leaveType);
    }
}
