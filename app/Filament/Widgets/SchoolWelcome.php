<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ClassAttendanceEntry;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Year;
use App\Services\SchoolOverviewService;
use Filament\Widgets\Widget;

/**
 * The top of the dashboard: a drawing of the school with a greeting, shortcuts, and the school in numbers
 * (branches, classes, students and teachers), each with its own little drawing.
 */
class SchoolWelcome extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -3;

    protected string $view = 'filament.widgets.school-welcome';

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
        $counts = app(SchoolOverviewService::class)->counts($user);
        $year = Year::current();
        $hour = now()->hour;

        return [
            'greeting' => match (true) {
                $hour < 12 => __('Good morning'),
                $hour < 17 => __('Good afternoon'),
                default => __('Good evening'),
            },
            'name' => $user?->firstname,
            'year' => $year,
            'schoolDay' => $year?->start !== null && $year->end !== null && now()->between($year->start, $year->end)
                ? (int) $year->start->diffInDays(today()) + 1
                : null,
            'counts' => $counts,
            'attendanceUrl' => ClassAttendanceEntry::canAccess() ? ClassAttendanceEntry::getUrl() : null,
            'admissionUrl' => ($user?->can('admissions.manage') ?? false) ? AdmissionResource::getUrl('create') : null,
            'cards' => [
                [
                    'art' => 'branch',
                    'label' => __('Branches'),
                    'value' => $counts['branches'],
                    'note' => implode(', ', $counts['branchNames']),
                    'tint' => 'bg-amber-50 dark:bg-amber-400/10',
                ],
                [
                    'art' => 'class',
                    'label' => __('Classes'),
                    'value' => $counts['classes'],
                    'note' => $counts['firstClass'] !== null ? $counts['firstClass'].' – '.$counts['lastClass'] : '',
                    'tint' => 'bg-emerald-50 dark:bg-emerald-400/10',
                ],
                [
                    'art' => 'student',
                    'label' => __('Students'),
                    'value' => $counts['students'],
                    'note' => trans_choice('in :count section|in :count sections', $counts['sections'], ['count' => $counts['sections']]),
                    'tint' => 'bg-sky-50 dark:bg-sky-400/10',
                ],
                [
                    'art' => 'teacher',
                    'label' => __('Teachers'),
                    'value' => $counts['teachers'],
                    'note' => __('Subject teachers'),
                    'tint' => 'bg-violet-50 dark:bg-violet-400/10',
                ],
            ],
        ];
    }
}
