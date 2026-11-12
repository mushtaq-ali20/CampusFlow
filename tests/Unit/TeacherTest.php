<?php

namespace Tests\Unit;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_teacher(): void
    {
        $teacher = Teacher::factory()->create();

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_teacher_belongs_to_user(): void
    {
        $teacher = Teacher::factory()->create();

        $this->assertInstanceOf(User::class, $teacher->user);
    }

    public function test_teacher_uses_soft_deletes(): void
    {
        $teacher = Teacher::factory()->create();
        $teacher->delete();

        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
    }
}
