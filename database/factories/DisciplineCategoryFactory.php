<?php

namespace Database\Factories;

use App\Enums\DisciplineConsequence;
use App\Enums\DisciplineKind;
use App\Models\DisciplineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplineCategory>
 */
class DisciplineCategoryFactory extends Factory
{
    /**
     * Define the model's default state: a violation that is only noted.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'kind' => DisciplineKind::Violation,
            'points' => fake()->numberBetween(1, 10),
            'consequence' => DisciplineConsequence::Noted,
            'is_active' => true,
        ];
    }

    public function merit(): static
    {
        return $this->state(fn (): array => [
            'kind' => DisciplineKind::Merit,
            'consequence' => null,
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn (): array => ['consequence' => DisciplineConsequence::WarningToParent]);
    }
}
