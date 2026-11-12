<?php

namespace App\Filament\Actions;

use App\Filament\Pages\TimetableManager;
use App\Models\TimetableEntry;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Asks whether to update a timetable row, and leads to the timetable configuration screen, by class and
 * section or by teacher. It works on a table row, or on an entry given in the arguments ({entry: id}).
 */
class ReconfigureTimetableAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reconfigure';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Update'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalHeading(__('Update this timetable?'))
            ->modalDescription(function (array $arguments, ?TimetableEntry $record): string {
                $entry = static::entry($record, $arguments);

                return __(':subject by :teacher to :section, :day, :period. Do you want to update or reconfigure the timetable?', [
                    'subject' => $entry->subject->name,
                    'teacher' => $entry->teacher->name,
                    'section' => $entry->section->full_name,
                    'day' => __(TimetableEntry::DAYS[$entry->day_of_week] ?? ''),
                    'period' => $entry->period->name,
                ]);
            })
            ->modalSubmitActionLabel(__('Reconfigure class and section'))
            ->extraModalFooterActions(function (array $arguments, ?TimetableEntry $record): array {
                $entry = static::entry($record, $arguments);

                return [
                    Action::make('reconfigureTeacher')
                        ->label(__('Reconfigure teacher'))
                        ->color('gray')
                        ->url(TimetableManager::urlFor(['mode' => TimetableManager::MODE_TEACHER, 'teacherId' => $entry->teacher_id])),
                ];
            })
            ->action(function (array $arguments, ?TimetableEntry $record, Component $livewire): void {
                $entry = static::entry($record, $arguments);

                $livewire->redirect(TimetableManager::urlFor(['mode' => TimetableManager::MODE_SECTION, 'sectionId' => $entry->section_id]));
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function entry(?TimetableEntry $record, array $arguments): TimetableEntry
    {
        return ($record ?? TimetableEntry::query()->findOrFail($arguments['entry'] ?? null))
            ->loadMissing(['subject', 'teacher', 'section.schoolClass', 'period']);
    }
}
