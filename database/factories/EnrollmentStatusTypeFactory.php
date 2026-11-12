<?php

namespace Database\Factories;

use App\Models\EnrollmentStatusType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentStatusType>
 */
class EnrollmentStatusTypeFactory extends Factory
{
    protected $model = EnrollmentStatusType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}
