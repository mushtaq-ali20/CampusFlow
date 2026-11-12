<?php

namespace App\Services;

use App\Enums\ClassAttendanceStatus;
use App\Enums\ExamType;
use App\Enums\FeeFrequency;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\ClassAttendance;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\SubjectResult;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The figures of the illustrated part of the dashboard: the school in numbers, today's attendance, the lessons
 * going on, the exams of the year, the classes with their students and what happened lately.
 * Staff attached to a branch only get the figures of their branch, for the current academic year.
 */
class SchoolOverviewService
{
    /**
     * @return array{branches: int, branchNames: list<string>, classes: int, firstClass: ?string, lastClass: ?string, sections: int, students: int, teachers: int}
     */
    public function counts(?User $user): array
    {
        $branchId = $user?->activeBranchId();
        $branches = Campus::query()
            ->when($branchId !== null, fn (Builder $query) => $query->whereKey($branchId))
            ->orderBy('id')
            ->pluck('name');
        $classes = SchoolClass::query()->ordered()->pluck('name');

        return [
            'branches' => $branches->count(),
            'branchNames' => $branches->values()->all(),
            'classes' => $classes->count(),
            'firstClass' => $classes->first(),
            'lastClass' => $classes->last(),
            'sections' => $this->sections($user)->count(),
            'students' => $this->admissions($user)->count(),
            'teachers' => Teacher::query()
                ->when($branchId !== null, fn (Builder $query) => $query->whereHas('campuses', fn (Builder $campuses) => $campuses->whereKey($branchId)))
                ->count(),
        ];
    }

    /**
     * Attendance of the last day it was marked, today or before.
     *
     * @return array{date: ?Carbon, isToday: bool, present: int, late: int, absent: int, leave: int, total: int, rate: float, unmarkedToday: int}
     */
    public function attendance(?User $user): array
    {
        $sections = $this->sections($user)->select('id');
        $latest = ClassAttendance::query()
            ->whereIn('section_id', $sections)
            ->whereDate('date', '<=', today()->toDateString())
            ->max('date');

        $counts = $latest === null ? collect() : ClassAttendance::query()
            ->whereIn('section_id', $this->sections($user)->select('id'))
            ->whereDate('date', $latest)
            ->selectRaw('status, COUNT(*) as students')
            ->groupBy('status')
            ->pluck('students', 'status');

        $markedToday = ClassAttendance::query()
            ->whereIn('section_id', $this->sections($user)->select('id'))
            ->whereDate('date', today()->toDateString())
            ->distinct()
            ->count('section_id');

        $present = (int) $counts->get(ClassAttendanceStatus::Present->value, 0);
        $late = (int) $counts->get(ClassAttendanceStatus::Late->value, 0);
        $total = (int) $counts->sum();
        $date = $latest === null ? null : Carbon::parse($latest);

        return [
            'date' => $date,
            'isToday' => $date?->isToday() ?? false,
            'present' => $present,
            'late' => $late,
            'absent' => (int) $counts->get(ClassAttendanceStatus::Absent->value, 0),
            'leave' => (int) $counts->get(ClassAttendanceStatus::Leave->value, 0),
            'total' => $total,
            'rate' => $total > 0 ? round(($present + $late) / $total * 100, 1) : 0.0,
            'unmarkedToday' => max(0, $this->sections($user)->count() - $markedToday),
        ];
    }

    /**
     * The lessons of the period going on, or of the next period today.
     *
     * @return array{state: string, period: ?TimetablePeriod, lessons: Collection<int, TimetableEntry>, more: int}
     */
    public function lessonsNow(?User $user, ?Carbon $now = null, int $limit = 4): array
    {
        $now ??= now();
        $time = $now->format('H:i:s');
        $periods = TimetablePeriod::query()->where('is_break', false)->ordered()->get();

        $period = $periods->first(fn (TimetablePeriod $period): bool => $period->start_time <= $time && $period->end_time > $time);
        $state = 'now';

        if ($period === null) {
            $period = $periods->first(fn (TimetablePeriod $period): bool => $period->start_time > $time);
            $state = 'next';
        }

        $lessons = $period === null || $now->isSunday() ? TimetableEntry::query()->whereRaw('1 = 0') : TimetableEntry::query()
            ->whereIn('section_id', $this->sections($user)->select('id'))
            ->where('day_of_week', $now->dayOfWeekIso)
            ->where('timetable_period_id', $period->id);

        $count = (clone $lessons)->count();

        return [
            'state' => $count === 0 ? 'none' : $state,
            'period' => $count === 0 ? null : $period,
            'lessons' => $lessons
                ->with(['subject', 'teacher', 'section.schoolClass', 'section.campus'])
                ->orderBy('section_id')
                ->limit($limit)
                ->get(),
            'more' => max(0, $count - $limit),
        ];
    }

    /**
     * For each exam of the year, the students with marks entered.
     *
     * @return list<array{exam: ExamType, students: int, withResults: int}>
     */
    public function exams(?User $user): array
    {
        $students = $this->admissions($user)->count();

        return array_map(fn (ExamType $exam): array => [
            'exam' => $exam,
            'students' => $students,
            'withResults' => SubjectResult::query()
                ->where('exam', $exam)
                ->whereIn('admission_id', $this->admissions($user)->select('id'))
                ->distinct()
                ->count('admission_id'),
        ], ExamType::cases());
    }

    /**
     * What happened lately: admissions, fee payments, attendance, marks and timetable changes, newest first.
     *
     * @return Collection<int, array{kind: string, title: string, detail: string, at: Carbon}>
     */
    public function activity(?User $user, int $limit = 8): Collection
    {
        $sections = fn (): Builder => $this->sections($user)->select('id');

        $admissions = Admission::query()
            ->visibleTo($user)
            ->with(['student', 'section.schoolClass', 'campus'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Admission $admission): array => [
                'kind' => 'admission',
                'title' => (string) $admission->student?->name,
                'detail' => __('admitted to :section, :branch', ['section' => $admission->section?->full_name, 'branch' => $admission->campus?->name]),
                'at' => $admission->created_at,
            ]);

        $payments = FeePayment::query()
            ->visibleTo($user)
            ->with('voucher.admission.student')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (FeePayment $payment): array => [
                'kind' => 'payment',
                'title' => FeeService::money((int) $payment->amount),
                'detail' => __('received from :student, receipt :receipt', ['student' => $payment->voucher?->admission?->student?->name, 'receipt' => $payment->receipt_number]),
                'at' => $payment->created_at,
            ]);

        $attendance = ClassAttendance::query()
            ->whereIn('section_id', $sections())
            ->selectRaw('section_id, date, MAX(created_at) as marked_at, COUNT(*) as students, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as absent', [ClassAttendanceStatus::Absent->value])
            ->groupBy('section_id', 'date')
            ->orderByDesc('marked_at')
            ->limit($limit)
            ->get();

        $results = SubjectResult::query()
            ->join('admissions', 'admissions.id', '=', 'subject_results.admission_id')
            ->whereIn('admissions.section_id', $sections())
            ->selectRaw('admissions.section_id as section_id, subject_results.exam as exam, subject_results.subject_id as subject_id, MAX(subject_results.updated_at) as saved_at, COUNT(*) as students')
            ->groupBy('admissions.section_id', 'subject_results.exam', 'subject_results.subject_id')
            ->orderByDesc('saved_at')
            ->limit($limit)
            ->with('subject')
            ->get();

        $timetables = TimetableEntry::query()
            ->whereIn('section_id', $sections())
            ->selectRaw('section_id, MAX(updated_at) as changed_at, COUNT(*) as lessons')
            ->groupBy('section_id')
            ->orderByDesc('changed_at')
            ->limit($limit)
            ->get();

        $sectionNames = Section::query()
            ->with(['schoolClass', 'campus'])
            ->whereKey($attendance->pluck('section_id')->merge($results->pluck('section_id'))->merge($timetables->pluck('section_id'))->unique()->all())
            ->get()
            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name.', '.$section->campus?->name]);

        return $admissions
            ->concat($payments)
            ->concat($attendance->map(fn (ClassAttendance $row): array => [
                'kind' => 'attendance',
                'title' => (string) $sectionNames->get($row->section_id),
                'detail' => __('attendance marked for :date: :present present, :absent absent', [
                    'date' => Carbon::parse($row->date)->format('j M'),
                    'present' => (int) $row->students - (int) $row->absent,
                    'absent' => (int) $row->absent,
                ]),
                'at' => Carbon::parse($row->marked_at),
            ]))
            ->concat($results->map(fn (SubjectResult $row): array => [
                'kind' => 'result',
                'title' => __(':exam marks', ['exam' => $row->exam?->getLabel()]),
                'detail' => __('saved for :subject, :section (:count students)', [
                    'subject' => $row->subject?->name,
                    'section' => $sectionNames->get($row->section_id),
                    'count' => (int) $row->students,
                ]),
                'at' => Carbon::parse($row->saved_at),
            ]))
            ->concat($timetables->map(fn (TimetableEntry $row): array => [
                'kind' => 'timetable',
                'title' => __('Timetable'),
                'detail' => __('of :section updated (:count lessons a week)', ['section' => $sectionNames->get($row->section_id), 'count' => (int) $row->lessons]),
                'at' => Carbon::parse($row->changed_at),
            ]))
            ->filter(fn (array $event): bool => $event['at'] !== null)
            ->sortByDesc(fn (array $event): int => $event['at']->getTimestamp())
            ->take($limit)
            ->values();
    }

    /**
     * A class with its sections and their students, each with the attendance of the last day it was marked.
     *
     * @return array{students: int, present: int, marked: int, subjects: int, monthlyFee: ?array{min: int, max: int}, sections: list<array{section: Section, students: Collection<int, array{admission: Admission, status: ?ClassAttendanceStatus}>, date: ?Carbon}>}
     */
    public function classSnapshot(?User $user, SchoolClass $class): array
    {
        $sections = $this->sections($user)
            ->where('school_class_id', $class->id)
            ->with(['campus', 'classTeacher'])
            ->orderBy('campus_id')
            ->orderBy('name')
            ->get();

        $snapshot = $sections->map(function (Section $section): array {
            $date = ClassAttendance::query()
                ->where('section_id', $section->id)
                ->whereDate('date', '<=', today()->toDateString())
                ->max('date');

            $statuses = $date === null ? collect() : ClassAttendance::query()
                ->where('section_id', $section->id)
                ->whereDate('date', $date)
                ->pluck('status', 'admission_id');

            $students = Admission::query()
                ->active()
                ->where('section_id', $section->id)
                ->with('student')
                ->orderBy('admission_number')
                ->get()
                ->map(fn (Admission $admission): array => [
                    'admission' => $admission,
                    'status' => $statuses->get($admission->id),
                ]);

            return ['section' => $section, 'students' => $students, 'date' => $date === null ? null : Carbon::parse($date)];
        });

        $students = $snapshot->flatMap(fn (array $section): Collection => $section['students']);
        $fees = FeeStructure::query()
            ->where('school_class_id', $class->id)
            ->where('year_id', Year::current()?->id)
            ->whereHas('feeHead', fn (Builder $query) => $query->where('frequency', FeeFrequency::Monthly))
            ->when($user?->activeBranchId() !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('campus_id')->orWhere('campus_id', $user->activeBranchId())))
            ->get()
            ->groupBy(fn (FeeStructure $structure): string => (string) $structure->campus_id)
            ->map(fn (Collection $structures): int => (int) $structures->sum('amount'));

        return [
            'students' => $students->count(),
            'present' => $students->filter(fn (array $student): bool => $student['status']?->isAttended() ?? false)->count(),
            'marked' => $students->filter(fn (array $student): bool => $student['status'] !== null)->count(),
            'subjects' => $class->subjects()->count(),
            'monthlyFee' => $fees->isEmpty() ? null : ['min' => (int) $fees->min(), 'max' => (int) $fees->max()],
            'sections' => $snapshot->all(),
        ];
    }

    /**
     * @return Builder<Section>
     */
    private function sections(?User $user): Builder
    {
        return Section::query()
            ->visibleTo($user)
            ->when(Year::current(), fn (Builder $query, Year $year) => $query->where('year_id', $year->id));
    }

    /**
     * @return Builder<Admission>
     */
    private function admissions(?User $user): Builder
    {
        return Admission::query()
            ->active()
            ->visibleTo($user)
            ->when(Year::current(), fn (Builder $query, Year $year) => $query->where('year_id', $year->id));
    }
}
