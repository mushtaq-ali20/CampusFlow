<?php

namespace Tests\Unit;

use App\Models\Skills\SkillType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_skill_type(): void
    {
        $type = SkillType::factory()->create();

        $this->assertDatabaseHas('skill_types', ['id' => $type->id]);
    }
}
