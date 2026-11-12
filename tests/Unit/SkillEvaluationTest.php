<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Skills\Skill;
use App\Models\Skills\SkillEvaluation;
use App\Models\Skills\SkillScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_skill_evaluation(): void
    {
        $evaluation = SkillEvaluation::factory()->create();

        $this->assertNotNull($evaluation);
        $this->assertDatabaseHas('skill_evaluations', ['enrollment_id' => $evaluation->enrollment_id, 'skill_id' => $evaluation->skill_id]);
    }

    public function test_skill_evaluation_belongs_to_skill(): void
    {
        $evaluation = SkillEvaluation::factory()->create();

        $this->assertInstanceOf(Skill::class, $evaluation->skill);
    }

    public function test_skill_evaluation_belongs_to_enrollment(): void
    {
        $evaluation = SkillEvaluation::factory()->create();

        $this->assertInstanceOf(Enrollment::class, $evaluation->enrollment);
    }

    public function test_skill_evaluation_belongs_to_skill_scale(): void
    {
        $evaluation = SkillEvaluation::factory()->create();

        $this->assertInstanceOf(SkillScale::class, $evaluation->skill_scale);
    }
}
