<?php

namespace Database\Factories;

use App\Enums\AppraisalStatus;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appraisal>
 */
class AppraisalFactory extends Factory
{
    /**
     * Define the model's default state: a draft evaluation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appraisal_cycle_id' => AppraisalCycle::factory(),
            'employee_profile_id' => EmployeeProfile::factory(),
            'campus_id' => fn (array $attributes): int => EmployeeProfile::query()->findOrFail($attributes['employee_profile_id'])->campus_id,
            'status' => AppraisalStatus::Draft,
        ];
    }
}
