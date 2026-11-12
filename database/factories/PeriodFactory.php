<?php

namespace Database\Factories;

use App\Models\Period;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    protected $model = Period::class;

    public function definition(): array
    {
        $start = fake()->date();

        return [
            'name' => fake()->unique()->numerify('P###'),
            'start' => Carbon::parse($start),
            'end' => Carbon::parse($start)->addDays(90),
            'year_id' => Year::factory(),
        ];
    }
}
