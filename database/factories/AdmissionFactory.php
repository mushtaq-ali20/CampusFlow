<?php

namespace Database\Factories;

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'section_id' => Section::factory(),
            'campus_id' => fn (array $attributes): int => Section::query()->findOrFail($attributes['section_id'])->campus_id,
            'school_class_id' => fn (array $attributes): int => Section::query()->findOrFail($attributes['section_id'])->school_class_id,
            'year_id' => fn (array $attributes): int => Section::query()->findOrFail($attributes['section_id'])->year_id,
            'admitted_on' => now()->toDateString(),
            'status' => AdmissionStatus::Active,
        ];
    }
}
