<?php

namespace Database\Factories;

use App\Models\Skills\SkillScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillScale>
 */
class SkillScaleFactory extends Factory
{
    protected $model = SkillScale::class;

    public function definition(): array
    {
        return [
            'shortname' => fake()->lexify('??'),
            'name' => fake()->word(),
            'value' => fake()->randomFloat(2, 0, 10),
        ];
    }
}
