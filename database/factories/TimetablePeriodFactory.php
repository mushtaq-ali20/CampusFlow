<?php

namespace Database\Factories;

use App\Models\TimetablePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetablePeriod>
 */
class TimetablePeriodFactory extends Factory
{
    protected $model = TimetablePeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = fake()->unique()->numberBetween(1, 1000);

        return [
            'name' => 'Period '.$order,
            'start_time' => '08:00',
            'end_time' => '08:40',
            'order' => $order,
            'is_break' => false,
        ];
    }
}
