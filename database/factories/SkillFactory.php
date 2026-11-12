<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\Skills\Skill;
use App\Models\Skills\SkillType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'default_weight' => fake()->randomFloat(2, 0, 1),
            'order' => fake()->randomDigitNotNull(),
            'skill_type_id' => SkillType::factory(),
            'level_id' => Level::factory(),
        ];
    }
}
