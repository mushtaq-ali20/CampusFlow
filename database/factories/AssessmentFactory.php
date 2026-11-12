<?php

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Enums\ExamType;
use App\Models\Assessment;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The branch is copied from the section when the assessment is saved.
 *
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state: a class test of 20 marks counting towards the quarterly exam.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'exam' => ExamType::Quarterly,
            'type' => AssessmentType::ClassTest,
            'title' => 'Class test 1',
            'total_marks' => 20,
            'held_on' => now()->toDateString(),
        ];
    }
}
