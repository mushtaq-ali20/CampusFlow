<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableEntry>
 */
class TimetableEntryFactory extends Factory
{
    protected $model = TimetableEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'timetable_period_id' => TimetablePeriod::factory(),
            'day_of_week' => 1,
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
        ];
    }
}
