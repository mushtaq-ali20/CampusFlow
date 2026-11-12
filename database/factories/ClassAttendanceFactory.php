<?php

namespace Database\Factories;

use App\Enums\ClassAttendanceStatus;
use App\Models\Admission;
use App\Models\ClassAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassAttendance>
 */
class ClassAttendanceFactory extends Factory
{
    protected $model = ClassAttendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'section_id' => fn (array $attributes): int => Admission::query()->findOrFail($attributes['admission_id'])->section_id,
            'date' => now()->toDateString(),
            'status' => ClassAttendanceStatus::Present,
        ];
    }
}
