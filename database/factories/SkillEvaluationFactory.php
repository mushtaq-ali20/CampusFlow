<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Skills\Skill;
use App\Models\Skills\SkillEvaluation;
use App\Models\Skills\SkillScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillEvaluation>
 */
class SkillEvaluationFactory extends Factory
{
    protected $model = SkillEvaluation::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'skill_id' => Skill::factory(),
            'skill_scale_id' => SkillScale::factory(),
        ];
    }
}
