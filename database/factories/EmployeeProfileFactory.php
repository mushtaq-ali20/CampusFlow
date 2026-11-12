<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * Define the model's default state: a permanent employee on a basic salary of 30,000.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campus_id' => Campus::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'designation' => 'Clerk',
            'department' => 'Administration',
            'employment_type' => EmploymentType::Permanent,
            'status' => EmployeeStatus::Active,
            'joined_on' => '2024-01-01',
            'basic_salary' => 30000,
        ];
    }

    public function left(string $on = '2026-01-31'): static
    {
        return $this->state(['status' => EmployeeStatus::Left, 'left_on' => $on]);
    }
}
