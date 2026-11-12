<?php

namespace Database\Factories;

use App\Models\AttendanceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceType>
 */
class AttendanceTypeFactory extends Factory
{
    protected $model = AttendanceType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}
