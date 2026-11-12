<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\OpeningStatus;
use App\Models\Campus;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOpening>
 */
class JobOpeningFactory extends Factory
{
    /**
     * Define the model's default state: an open vacancy for one teacher.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'title' => 'Maths Teacher',
            'department' => 'Teaching',
            'employment_type' => EmploymentType::Permanent,
            'positions' => 1,
            'status' => OpeningStatus::Open,
        ];
    }
}
