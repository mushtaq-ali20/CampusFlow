<?php

namespace Tests\Unit;

use App\Models\Level;
use App\Models\Skills\Skill;
use App\Models\Skills\SkillType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_skill(): void
    {
        $skill = Skill::factory()->create();

        $this->assertDatabaseHas('skills', ['id' => $skill->id]);
    }

    public function test_skill_belongs_to_skill_type(): void
    {
        $skill = Skill::factory()->create();

        $this->assertInstanceOf(SkillType::class, $skill->skillType);
    }

    public function test_skill_belongs_to_level(): void
    {
        $skill = Skill::factory()->create();

        $this->assertInstanceOf(Level::class, $skill->level);
    }
}
