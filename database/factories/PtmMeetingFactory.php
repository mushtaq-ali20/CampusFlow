<?php

namespace Database\Factories;

use App\Enums\PtmMeetingStatus;
use App\Models\Admission;
use App\Models\PtmEvent;
use App\Models\PtmMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PtmMeeting>
 */
class PtmMeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ptm_event_id' => PtmEvent::factory(),
            'admission_id' => Admission::factory(),
            'section_id' => fn (array $attributes): int => Admission::query()->findOrFail($attributes['admission_id'])->section_id,
            'slot_time' => '09:00:00',
            'status' => PtmMeetingStatus::Scheduled,
        ];
    }
}
