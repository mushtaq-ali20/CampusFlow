<?php

namespace App\Filament\Widgets;

use App\Services\SchoolOverviewService;
use Filament\Widgets\Widget;

/**
 * What happened lately at school: admissions, fee payments, attendance, marks and timetable changes.
 * Staff who do not see the fees do not get the payments.
 */
class SchoolActivity extends Widget
{
    public const EVENTS = 8;

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.school-activity';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

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
        $canSeeFees = $user?->can('fees.view') ?? false;

        return [
            'events' => app(SchoolOverviewService::class)
                ->activity($user, self::EVENTS + ($canSeeFees ? 0 : self::EVENTS))
                ->reject(fn (array $event): bool => ! $canSeeFees && $event['kind'] === 'payment')
                ->take(self::EVENTS),
        ];
    }
}
