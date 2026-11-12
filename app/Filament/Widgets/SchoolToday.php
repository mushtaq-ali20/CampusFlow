<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ClassAttendanceEntry;
use App\Filament\Pages\MarksEntry;
use App\Models\Year;
use App\Services\FeeReportService;
use App\Services\SchoolOverviewService;
use Filament\Widgets\Widget;

/**
 * Today at school: the attendance of the day, the lessons going on, the three exams of the year, and the fee
 * of this month (only for staff who see the fees).
 */
class SchoolToday extends Widget
{
    /**
     * The length of the attendance ring: 2πr for a radius of 42.
     */
    public const RING_LENGTH = 264;

    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    protected string $view = 'filament.widgets.school-today';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('school.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $overview = app(SchoolOverviewService::class);
        $attendance = $overview->attendance($user);
        $showFees = $user?->can('fees.view') ?? false;

        return [
            'attendance' => $attendance,
            'ringOffset' => round(self::RING_LENGTH * (1 - $attendance['rate'] / 100), 1),
            'attendanceUrl' => ClassAttendanceEntry::canAccess() ? ClassAttendanceEntry::getUrl() : null,
            'lessons' => $overview->lessonsNow($user),
            'exams' => $overview->exams($user),
            'marksUrl' => MarksEntry::canAccess() ? MarksEntry::getUrl() : null,
            'showFees' => $showFees,
            'fees' => $showFees ? app(FeeReportService::class)->totals([
                'year_id' => Year::current()?->id,
                'month' => now()->startOfMonth()->toDateString(),
            ], $user) : null,
            'tones' => ['sky', 'violet', 'emerald', 'amber'],
        ];
    }
}
