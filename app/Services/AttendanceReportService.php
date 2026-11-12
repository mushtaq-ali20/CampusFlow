<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\ClassAttendanceStatus;
use App\Models\Admission;
use App\Models\ClassAttendance;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reports on the daily attendance taken by the class teachers: each section on a day, the absent or late students
 * over a period, and the attendance of each student over a month. Staff attached to a branch only see their branch.
 */
class AttendanceReportService
{
    /**
     * The sections with active students, with how many students were present, absent, late or on leave on the day,
     * and how many have their attendance taken (none when the class teacher has not submitted it).
     *
     * @return Builder<Section>
     */
    public function classWise(CarbonInterface $date, ?User $user): Builder
    {
        $onDate = fn (?ClassAttendanceStatus $status = null): Closure => fn (Builder $query): Builder => $query
            ->whereDate('date', $date->toDateString())
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status));

        return Section::query()
            ->visibleTo($user)
            ->whereHas('admissions', fn (Builder $admissions) => $admissions->where('status', AdmissionStatus::Active))
            ->withCount([
                'admissions as students_count' => fn (Builder $admissions): Builder => $admissions->where('status', AdmissionStatus::Active),
                'classAttendances as recorded_count' => $onDate(),
                'classAttendances as present_count' => $onDate(ClassAttendanceStatus::Present),
                'classAttendances as absent_count' => $onDate(ClassAttendanceStatus::Absent),
                'classAttendances as late_count' => $onDate(ClassAttendanceStatus::Late),
                'classAttendances as leave_count' => $onDate(ClassAttendanceStatus::Leave),
            ])
            ->with(['campus', 'schoolClass', 'classTeacher.user'])
            ->orderBy('campus_id')
            ->orderBy(SchoolClass::query()->select('order')->whereColumn('school_classes.id', 'sections.school_class_id'))
            ->orderBy('name');
    }

    /**
     * The attendance records with one status (such as the absent students) between two dates, optionally of one section.
     *
     * @return Builder<ClassAttendance>
     */
    public function withStatus(ClassAttendanceStatus $status, CarbonInterface $from, CarbonInterface $until, ?User $user, ?int $sectionId = null): Builder
    {
        return ClassAttendance::query()
            ->where('status', $status)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $until->toDateString())
            ->whereHas('admission', fn (Builder $admissions) => $admissions->visibleTo($user))
            ->when($sectionId !== null, fn (Builder $query): Builder => $query->where('section_id', $sectionId))
            ->with(['admission.student', 'section.schoolClass'])
            ->orderByDesc('date')
            ->orderBy('section_id');
    }

    /**
     * The active students with how many days of the month they were present, absent, late or on leave.
     *
     * @return Builder<Admission>
     */
    public function monthly(CarbonInterface $month, ?User $user, ?int $sectionId = null): Builder
    {
        $inMonth = fn (?ClassAttendanceStatus $status = null): Closure => fn (Builder $query): Builder => $query
            ->whereDate('date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status));

        return Admission::query()
            ->visibleTo($user)
            ->where('status', AdmissionStatus::Active)
            ->when($sectionId !== null, fn (Builder $query): Builder => $query->where('section_id', $sectionId))
            ->withCount([
                'attendances as recorded_count' => $inMonth(),
                'attendances as present_count' => $inMonth(ClassAttendanceStatus::Present),
                'attendances as absent_count' => $inMonth(ClassAttendanceStatus::Absent),
                'attendances as late_count' => $inMonth(ClassAttendanceStatus::Late),
                'attendances as leave_count' => $inMonth(ClassAttendanceStatus::Leave),
            ])
            ->with(['student', 'section.schoolClass'])
            ->orderBy('section_id')
            ->orderBy('admission_number');
    }

    /**
     * The share of days attended (present or late) out of the days recorded, or null when nothing was recorded.
     */
    public static function percentage(int $present, int $late, int $recorded): ?float
    {
        return $recorded > 0 ? round(($present + $late) * 100 / $recorded, 1) : null;
    }
}
