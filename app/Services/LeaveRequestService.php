<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\EmployeeProfile;
use App\Models\Leave;
use App\Models\LeaveAllowance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The rules of the leave requests of the employees: how many days a request takes, what is left of a leave type,
 * and what approving a request does.
 */
class LeaveRequestService
{
    /**
     * The days of a period that count as leave: all of them except the weekly days off of the school.
     */
    public function workingDays(CarbonInterface $from, CarbonInterface $to): int
    {
        $off = array_map('intval', (array) config('campusflow.weekly_off_days', [0]));
        $days = 0;

        for ($day = CarbonImmutable::parse($from)->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeek, $off, true)) {
                $days++;
            }
        }

        return $days;
    }

    public function isPaid(LeaveType $type): bool
    {
        return LeaveAllowance::query()->where('leave_type_id', $type->id)->value('is_paid') ?? true;
    }

    /**
     * What an employee may still take of a leave type in a year.
     *
     * @return array{allowed: ?int, used: int, remaining: ?int, paid: bool}
     */
    public function balance(User $employee, LeaveType $type, ?int $year = null): array
    {
        $year ??= now()->year;
        $allowance = LeaveAllowance::query()->where('leave_type_id', $type->id)->first();
        $used = (int) LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('status', RequestStatus::Approved)
            ->whereYear('from_date', $year)
            ->sum('days');
        $allowed = $allowance !== null && $allowance->days_per_year > 0 ? $allowance->days_per_year : null;

        return [
            'allowed' => $allowed,
            'used' => $used,
            'remaining' => $allowed === null ? null : max(0, $allowed - $used),
            'paid' => $allowance?->is_paid ?? true,
        ];
    }

    /**
     * Records a request for leave. It waits for approval.
     *
     * @throws DomainException
     */
    public function request(User $employee, LeaveType $type, CarbonInterface $from, CarbonInterface $to, ?string $reason = null): LeaveRequest
    {
        $profile = EmployeeProfile::query()->where('user_id', $employee->id)->first();

        if ($profile === null) {
            throw new DomainException(__('This person has no employee file yet.'));
        }

        if ($to->lt($from)) {
            throw new DomainException(__('The last day cannot be before the first day.'));
        }

        $days = $this->workingDays($from, $to);

        if ($days === 0) {
            throw new DomainException(__('These dates are all days off.'));
        }

        $overlaps = LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::Approved])
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->exists();

        if ($overlaps) {
            throw new DomainException(__('There is already a leave request for some of these days.'));
        }

        $this->assertWithinAllowance($employee, $type, $days, $from->year);

        return LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'campus_id' => $profile->campus_id,
            'leave_type_id' => $type->id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'days' => $days,
            'reason' => $reason,
            'status' => RequestStatus::Pending,
        ]);
    }

    /**
     * Approves the request. The leave of a teacher also goes on the timetable, which finds a substitute.
     *
     * @throws DomainException
     */
    public function approve(LeaveRequest $request, User $by): LeaveRequest
    {
        $this->assertPending($request);
        $this->assertWithinAllowance($request->user, $request->leaveType, $request->days, $request->from_date->year);

        DB::transaction(function () use ($request, $by): void {
            $request->update(['status' => RequestStatus::Approved, 'decided_by' => $by->id, 'decided_at' => now()]);
            $this->giveTeacherLeaves($request);
        });

        return $request->refresh();
    }

    /**
     * @throws DomainException
     */
    public function reject(LeaveRequest $request, User $by, ?string $note = null): LeaveRequest
    {
        $this->assertPending($request);

        $request->update(['status' => RequestStatus::Rejected, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);

        return $request->refresh();
    }

    /**
     * Withdraws a request that nobody has decided yet.
     *
     * @throws DomainException
     */
    public function cancel(LeaveRequest $request): void
    {
        $this->assertPending($request);

        $request->delete();
    }

    /**
     * The days of unpaid leave an employee has approved in a month.
     */
    public function unpaidDays(User $employee, CarbonInterface $month): int
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();
        $unpaidTypes = LeaveAllowance::query()->where('is_paid', false)->pluck('leave_type_id');

        if ($unpaidTypes->isEmpty()) {
            return 0;
        }

        return LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->where('status', RequestStatus::Approved)
            ->whereIn('leave_type_id', $unpaidTypes)
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $start)
            ->get()
            ->sum(fn (LeaveRequest $request): int => $this->workingDays(
                $request->from_date->max($start),
                $request->to_date->min($end),
            ));
    }

    /**
     * @throws DomainException
     */
    private function assertPending(LeaveRequest $request): void
    {
        if ($request->status !== RequestStatus::Pending) {
            throw new DomainException(__('This request has been decided already.'));
        }
    }

    /**
     * @throws DomainException
     */
    private function assertWithinAllowance(User $employee, LeaveType $type, int $days, int $year): void
    {
        $balance = $this->balance($employee, $type, $year);

        if ($balance['remaining'] !== null && $days > $balance['remaining']) {
            throw new DomainException(__('Only :days days of :type are left this year.', ['days' => $balance['remaining'], 'type' => $type->name]));
        }
    }

    private function giveTeacherLeaves(LeaveRequest $request): void
    {
        $teacher = Teacher::query()->find($request->user_id);

        if ($teacher === null) {
            return;
        }

        $off = array_map('intval', (array) config('campusflow.weekly_off_days', [0]));

        for ($day = CarbonImmutable::parse($request->from_date); $day->lte($request->to_date); $day = $day->addDay()) {
            if (in_array($day->dayOfWeek, $off, true)) {
                continue;
            }

            Leave::query()->create([
                'teacher_id' => $teacher->id,
                'leave_type_id' => $request->leave_type_id,
                'date' => $day->toDateString(),
            ]);
        }
    }
}
