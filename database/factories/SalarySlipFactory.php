<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalarySlip>
 */
class SalarySlipFactory extends Factory
{
    /**
     * Define the model's default state: an unpaid slip of 30,000 with no allowances or deductions.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_profile_id' => EmployeeProfile::factory(),
            'campus_id' => fn (array $attributes): int => PayrollRun::query()->findOrFail($attributes['payroll_run_id'])->campus_id,
            'slip_number' => 'SL-'.fake()->unique()->numerify('######-####'),
            'basic_salary' => 30000,
            'net_salary' => 30000,
        ];
    }
}
