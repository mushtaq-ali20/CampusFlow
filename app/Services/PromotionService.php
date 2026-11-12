<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\ExamType;
use App\Models\Admission;
use App\Models\Section;
use App\Models\Subject;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A student who passes all the subjects of the class in the annual exam is promoted to the next class.
 * The quarterly and half yearly exams are recorded but do not decide the promotion.
 */
class PromotionService
{
    /**
     * The subjects of the class that the student passed, failed, or has no annual exam marks for yet.
     *
     * @return array{passed: bool, passedSubjects: Collection<int, Subject>, failedSubjects: Collection<int, Subject>, missingSubjects: Collection<int, Subject>}
     */
    public function evaluate(Admission $admission): array
    {
        $subjects = $admission->schoolClass->subjects;
        $marks = $admission->results()->where('exam', ExamType::Annual)->pluck('obtained_marks', 'subject_id');

        $passedSubjects = collect();
        $failedSubjects = collect();
        $missingSubjects = collect();

        foreach ($subjects as $subject) {
            if (! $marks->has($subject->id)) {
                $missingSubjects->push($subject);
            } elseif ((float) $marks[$subject->id] >= $subject->pivot->passing_marks) {
                $passedSubjects->push($subject);
            } else {
                $failedSubjects->push($subject);
            }
        }

        return [
            'passed' => $subjects->isNotEmpty() && $failedSubjects->isEmpty() && $missingSubjects->isEmpty(),
            'passedSubjects' => $passedSubjects,
            'failedSubjects' => $failedSubjects,
            'missingSubjects' => $missingSubjects,
        ];
    }

    public function canPromote(Admission $admission): bool
    {
        return $admission->status === AdmissionStatus::Active
            && $admission->schoolClass->nextClass() !== null
            && $this->evaluate($admission)['passed'];
    }

    /**
     * Admits the student to a section of the next class, at the same branch and in another academic year.
     *
     * @throws DomainException
     */
    public function promote(Admission $admission, Section $section): Admission
    {
        if ($admission->status !== AdmissionStatus::Active) {
            throw new DomainException(__('Only active admissions can be promoted.'));
        }

        $nextClass = $admission->schoolClass->nextClass();

        if ($nextClass === null) {
            throw new DomainException(__('This is the last class: there is no class to promote the student to.'));
        }

        if (! $this->evaluate($admission)['passed']) {
            throw new DomainException(__('The student has not passed all the subjects in the annual exam.'));
        }

        if ($section->school_class_id !== $nextClass->id) {
            throw new DomainException(__('The section must belong to :class.', ['class' => $nextClass->name]));
        }

        if ($section->campus_id !== $admission->campus_id) {
            throw new DomainException(__('The section must belong to the same branch.'));
        }

        if ($section->year_id === $admission->year_id) {
            throw new DomainException(__('The section must belong to another academic year.'));
        }

        if (Admission::query()->where('student_id', $admission->student_id)->where('year_id', $section->year_id)->exists()) {
            throw new DomainException(__('The student is already admitted in this academic year.'));
        }

        return DB::transaction(function () use ($admission, $section): Admission {
            $promoted = Admission::query()->create([
                'student_id' => $admission->student_id,
                'campus_id' => $section->campus_id,
                'school_class_id' => $section->school_class_id,
                'section_id' => $section->id,
                'year_id' => $section->year_id,
                'admitted_on' => now()->toDateString(),
                'status' => AdmissionStatus::Active,
            ]);

            $promoted->discounts()->sync($admission->discounts()->pluck('fee_discounts.id'));
            $admission->update(['status' => AdmissionStatus::Promoted]);

            return $promoted;
        });
    }
}
