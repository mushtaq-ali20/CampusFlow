<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\ScheduledPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledPayment>
 */
class ScheduledPaymentFactory extends Factory
{
    protected $model = ScheduledPayment::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'value' => fake()->numberBetween(1000, 100000),
            'date' => fake()->date(),
            'status' => fake()->optional()->randomDigit(),
        ];
    }
}
