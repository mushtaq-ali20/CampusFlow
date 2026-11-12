<?php

namespace Database\Factories;

use App\Enums\ExamType;
use App\Models\Admission;
use App\Models\Subject;
use App\Models\SubjectResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectResult>
 */
class SubjectResultFactory extends Factory
{
    protected $model = SubjectResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'subject_id' => Subject::factory(),
            'exam' => ExamType::Annual,
            'obtained_marks' => fake()->numberBetween(20, 95),
        ];
    }
}
