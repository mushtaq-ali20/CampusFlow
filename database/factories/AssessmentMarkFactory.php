<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Assessment;
use App\Models\AssessmentMark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentMark>
 */
class AssessmentMarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'admission_id' => Admission::factory(),
            'marks' => fake()->numberBetween(5, 20),
        ];
    }
}
