<?php

namespace Database\Factories;

use App\Enums\ApplicantStage;
use App\Models\JobApplicant;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplicant>
 */
class JobApplicantFactory extends Factory
{
    /**
     * Define the model's default state: a new application.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_opening_id' => JobOpening::factory(),
            'campus_id' => fn (array $attributes): int => JobOpening::query()->findOrFail($attributes['job_opening_id'])->campus_id,
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'phone' => '0300-1234567',
            'email' => fake()->unique()->safeEmail(),
            'qualification' => 'M.Sc.',
            'experience_years' => 3,
            'expected_salary' => 40000,
            'stage' => ApplicantStage::Applied,
        ];
    }
}
