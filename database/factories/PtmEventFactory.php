<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\PtmEvent;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PtmEvent>
 */
class PtmEventFactory extends Factory
{
    /**
     * Define the model's default state: a meeting day next week from 9:00, ten minutes per family.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Mid-term PTM',
            'campus_id' => Campus::factory(),
            'year_id' => Year::factory(),
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '09:00:00',
            'slot_minutes' => 10,
        ];
    }
}
