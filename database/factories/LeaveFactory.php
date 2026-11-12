<?php

namespace Database\Factories;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'date' => fake()->date(),
            'leave_type_id' => LeaveType::factory(),
        ];
    }
}
