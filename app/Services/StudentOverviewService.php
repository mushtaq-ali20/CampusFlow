<?php

namespace App\Services;

use App\Enums\ClassAttendanceStatus;
use App\Enums\ExamType;
use App\Models\Admission;
use App\Models\ClassAttendance;
use App\Models\DisciplineRecord;
use App\Models\FeeVoucher;
use App\Models\Student;
use App\Models\SubjectResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * A student at a glance, for their profile: where they study now, their attendance and their latest exam this
 * year, what they owe, and their behaviour points. Staff attached to a branch only count the admissions of their
 * branch.
 */
class StudentOverviewService
{
    /**
     * @return array{
     *     admission: ?Admission,
     *     attendance: array{attended: int, total: int, percentage: ?float},
     *     exam: array{exam: ?ExamType, percentage: ?float},
     *     feeBalance: int,
     *     disciplinePoints: int,
     * }
     */
    public function summary(Student $student, ?User $user): array
    {
        $admission = $this->admissions($student, $user)
            ->with(['campus', 'section.schoolClass', 'schoolClass.subjects'])
            ->orderByDesc('year_id')
            ->orderByDesc('id')
            ->first();

        return [
            'admission' => $admission,
            'attendance' => $this->attendance($admission),
            'exam' => $this->latestExam($admission),
            'feeBalance' => $this->feeBalance($student, $user),
            'disciplinePoints' => $admission ? (int) DisciplineRecord::query()->where('admission_id', $admission->id)->sum('points') : 0,
        ];
    }

    /**
     * The days the student attended (present or late) out of the days attendance was taken, in the admission.
     *
     * @return array{attended: int, total: int, percentage: ?float}
     */
    public function attendance(?Admission $admission): array
    {
        if ($admission === null) {
            return ['attended' => 0, 'total' => 0, 'percentage' => null];
        }

        $statuses = ClassAttendance::query()->where('admission_id', $admission->id)->pluck('status');
        $attended = $statuses->filter(fn (ClassAttendanceStatus $status): bool => $status->isAttended())->count();
        $total = $statuses->count();

        return [
            'attended' => $attended,
            'total' => $total,
            'percentage' => $total > 0 ? round($attended * 100 / $total, 1) : null,
        ];
    }

    /**
     * The percentage of the latest exam of the admission with marks: the marks obtained out of the total marks of
     * the subjects that have marks.
     *
     * @return array{exam: ?ExamType, percentage: ?float}
     */
    public function latestExam(?Admission $admission): array
    {
        if ($admission === null) {
            return ['exam' => null, 'percentage' => null];
        }

        $results = SubjectResult::query()->where('admission_id', $admission->id)->get()->groupBy(fn (SubjectResult $result): string => $result->exam->value);
        $exam = collect([ExamType::Annual, ExamType::HalfYearly, ExamType::Quarterly])->first(fn (ExamType $exam): bool => $results->has($exam->value));

        if ($exam === null) {
            return ['exam' => null, 'percentage' => null];
        }

        $totalMarks = $admission->schoolClass->subjects->mapWithKeys(fn ($subject): array => [$subject->id => (int) $subject->pivot->total_marks]);
        $marked = $results[$exam->value]->filter(fn (SubjectResult $result): bool => $totalMarks->has($result->subject_id));
        $total = $marked->sum(fn (SubjectResult $result): int => $totalMarks[$result->subject_id]);

        return [
            'exam' => $exam,
            'percentage' => $total > 0 ? round($marked->sum('obtained_marks') * 100 / $total, 1) : null,
        ];
    }

    /**
     * What the student still owes on all their vouchers.
     */
    public function feeBalance(Student $student, ?User $user): int
    {
        $vouchers = FeeVoucher::query()->whereIn('admission_id', $this->admissions($student, $user)->select('id'));

        return (int) (clone $vouchers)->sum('net_amount') - (int) (clone $vouchers)->sum('paid_amount');
    }

    /**
     * @return Builder<Admission>
     */
    private function admissions(Student $student, ?User $user): Builder
    {
        return Admission::query()->where('student_id', $student->id)->visibleTo($user);
    }
}
