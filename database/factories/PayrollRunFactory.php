<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Campus;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * Define the model's default state: a draft payroll for March 2026.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'month' => '2026-03',
            'status' => PayrollStatus::Draft,
        ];
    }
}
