<?php

namespace App\Filament\Concerns;

use App\Models\Section;
use App\Models\TimetableEntry;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Timetable lessons for the dashboard filters (branch, class, section, teacher and academic year).
 * Branch staff only get the lessons of their branch.
 */
trait FiltersTimetableByDashboard
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return auth()->user()?->can('school.view') ?? false;
    }

    /**
     * The colours of a subject's lessons, the same wherever the subject appears.
     *
     * @return array{cell: string, subject: string}
     */
    public function subjectColours(?int $subjectId): array
    {
        $colours = [
            ['cell' => 'bg-sky-100 ring-sky-200 hover:bg-sky-200 dark:bg-sky-400/10 dark:ring-sky-400/20 dark:hover:bg-sky-400/20', 'subject' => 'text-sky-700 dark:text-sky-300'],
            ['cell' => 'bg-violet-100 ring-violet-200 hover:bg-violet-200 dark:bg-violet-400/10 dark:ring-violet-400/20 dark:hover:bg-violet-400/20', 'subject' => 'text-violet-700 dark:text-violet-300'],
            ['cell' => 'bg-emerald-100 ring-emerald-200 hover:bg-emerald-200 dark:bg-emerald-400/10 dark:ring-emerald-400/20 dark:hover:bg-emerald-400/20', 'subject' => 'text-emerald-700 dark:text-emerald-300'],
            ['cell' => 'bg-amber-100 ring-amber-200 hover:bg-amber-200 dark:bg-amber-400/10 dark:ring-amber-400/20 dark:hover:bg-amber-400/20', 'subject' => 'text-amber-800 dark:text-amber-300'],
            ['cell' => 'bg-rose-100 ring-rose-200 hover:bg-rose-200 dark:bg-rose-400/10 dark:ring-rose-400/20 dark:hover:bg-rose-400/20', 'subject' => 'text-rose-700 dark:text-rose-300'],
            ['cell' => 'bg-teal-100 ring-teal-200 hover:bg-teal-200 dark:bg-teal-400/10 dark:ring-teal-400/20 dark:hover:bg-teal-400/20', 'subject' => 'text-teal-700 dark:text-teal-300'],
            ['cell' => 'bg-orange-100 ring-orange-200 hover:bg-orange-200 dark:bg-orange-400/10 dark:ring-orange-400/20 dark:hover:bg-orange-400/20', 'subject' => 'text-orange-800 dark:text-orange-300'],
            ['cell' => 'bg-indigo-100 ring-indigo-200 hover:bg-indigo-200 dark:bg-indigo-400/10 dark:ring-indigo-400/20 dark:hover:bg-indigo-400/20', 'subject' => 'text-indigo-700 dark:text-indigo-300'],
        ];

        return $colours[($subjectId ?? 0) % count($colours)];
    }

    /**
     * @return Builder<TimetableEntry>
     */
    protected function lessons(): Builder
    {
        $filters = $this->pageFilters ?? [];

        $sections = Section::query()
            ->visibleTo(auth()->user())
            ->when(filled($filters['campus_id'] ?? null), fn (Builder $query) => $query->where('campus_id', $filters['campus_id']))
            ->when(filled($filters['school_class_id'] ?? null), fn (Builder $query) => $query->where('school_class_id', $filters['school_class_id']))
            ->when(filled($filters['section_id'] ?? null), fn (Builder $query) => $query->whereKey($filters['section_id']))
            ->when(filled($filters['year_id'] ?? null), fn (Builder $query) => $query->where('year_id', $filters['year_id']))
            ->select('id');

        return TimetableEntry::query()
            ->whereIn('section_id', $sections)
            ->when(filled($filters['teacher_id'] ?? null), fn (Builder $query) => $query->where('teacher_id', $filters['teacher_id']));
    }
}
