<?php

namespace Database\Factories;

use App\Models\Skills\SkillType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillType>
 */
class SkillTypeFactory extends Factory
{
    protected $model = SkillType::class;

    public function definition(): array
    {
        return [
            'shortname' => fake()->lexify('??'),
            'name' => fake()->word(),
        ];
    }
}
