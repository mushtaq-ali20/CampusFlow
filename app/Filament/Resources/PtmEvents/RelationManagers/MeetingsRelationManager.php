<?php

namespace App\Filament\Resources\PtmEvents\RelationManagers;

use App\Enums\PtmMeetingStatus;
use App\Filament\Actions\SendWhatsAppAction;
use App\Models\PtmMeeting;
use App\Models\Student;
use App\Services\PtmService;
use App\Services\StudentOverviewService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The slots of the meeting day, with the attendance and the latest exam of each student for the teacher to discuss.
 * The class teacher of the section, or staff who manage meetings, invite the parents, record the meeting and follow
 * up on the actions agreed. Class teachers only see the slots of their sections.
 */
class MeetingsRelationManager extends RelationManager
{
    protected static string $relationship = 'meetings';

    /**
     * Meetings are recorded from the meeting day page, for which the slots are not read-only.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Slots');
    }

    protected function canActOn(PtmMeeting $meeting): bool
    {
        $user = auth()->user();

        return ($user?->can('ptm.manage') ?? false) || ($user !== null && (int) $meeting->section->class_teacher_id === (int) $user->id);
    }

    public function table(Table $table): Table
    {
        $overview = app(StudentOverviewService::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['admission.student', 'admission.schoolClass.subjects', 'section.schoolClass', 'teacher.user'])
                ->when(! (auth()->user()?->can('ptm.view') ?? false), fn (Builder $query) => $query->whereHas('section', fn (Builder $sections) => $sections->where('class_teacher_id', auth()->id()))))
            ->defaultSort(fn (Builder $query) => $query->orderBy('section_id')->orderBy('slot_time'))
            ->columns([
                TextColumn::make('slot_time')
                    ->label(__('Slot'))
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->format('h:i A')),
                TextColumn::make('admission.student.name')
                    ->label(__('Student'))
                    ->description(fn (PtmMeeting $record): string => $record->section->full_name),
                TextColumn::make('teacher.name')
                    ->label(__('Class teacher'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('attendance')
                    ->label(__('Attendance'))
                    ->state(fn (PtmMeeting $record): ?string => ($percentage = $overview->attendance($record->admission)['percentage']) !== null ? $percentage.'%' : null)
                    ->placeholder('—'),
                TextColumn::make('latest_exam')
                    ->label(__('Latest exam'))
                    ->state(function (PtmMeeting $record) use ($overview): ?string {
                        $exam = $overview->latestExam($record->admission);

                        return $exam['percentage'] !== null ? $exam['percentage'].'% · '.$exam['exam']->getLabel() : null;
                    })
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('action_items')
                    ->label(__('Actions agreed'))
                    ->description(fn (PtmMeeting $record): ?string => match (true) {
                        $record->action_done_at !== null => __('Done'),
                        $record->action_due_on !== null => __('Review on :date', ['date' => $record->action_due_on->format('d M Y')]),
                        default => null,
                    })
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->relationship('section', 'name', fn (Builder $query) => $query->with('schoolClass'))
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PtmMeetingStatus::class),
            ])
            ->recordActions([
                SendWhatsAppAction::make()
                    ->label(__('Invite'))
                    ->student(fn (PtmMeeting $record): Student => $record->admission->student)
                    ->message(fn (PtmMeeting $record): string => app(PtmService::class)->invitation($record))
                    ->aboutRecord()
                    ->after(function (PtmMeeting $record): void {
                        if ($record->status === PtmMeetingStatus::Scheduled) {
                            $record->update(['status' => PtmMeetingStatus::Invited]);
                        }
                    }),
                Action::make('record')
                    ->label(__('Record meeting'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (PtmMeeting $record): bool => $this->canActOn($record))
                    ->fillForm(fn (PtmMeeting $record): array => [
                        'notes' => $record->notes,
                        'action_items' => $record->action_items,
                        'action_due_on' => $record->action_due_on,
                    ])
                    ->schema([
                        Textarea::make('notes')->label(__('What was discussed'))->rows(4),
                        Textarea::make('action_items')->label(__('Actions agreed'))->rows(3),
                        DatePicker::make('action_due_on')->label(__('Review on')),
                    ])
                    ->action(function (PtmMeeting $record, array $data): void {
                        app(PtmService::class)->recordMeeting($record, $data['notes'] ?? null, $data['action_items'] ?? null, $data['action_due_on'] ?? null);
                        Notification::make()->title(__('Meeting recorded'))->success()->send();
                    }),
                ActionGroup::make([
                    Action::make('confirm')
                        ->label(__('Parents confirmed'))
                        ->icon(Heroicon::OutlinedHandThumbUp)
                        ->visible(fn (PtmMeeting $record): bool => $this->canActOn($record) && in_array($record->status, [PtmMeetingStatus::Scheduled, PtmMeetingStatus::Invited], true))
                        ->action(fn (PtmMeeting $record) => $record->update(['status' => PtmMeetingStatus::Confirmed])),
                    Action::make('noShow')
                        ->label(__('Did not come'))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->visible(fn (PtmMeeting $record): bool => $this->canActOn($record) && $record->status !== PtmMeetingStatus::Met)
                        ->action(fn (PtmMeeting $record) => $record->update(['status' => PtmMeetingStatus::NoShow])),
                    Action::make('actionDone')
                        ->label(__('Actions done'))
                        ->icon(Heroicon::OutlinedCheck)
                        ->visible(fn (PtmMeeting $record): bool => $this->canActOn($record) && $record->hasOpenAction())
                        ->action(fn (PtmMeeting $record) => $record->update(['action_done_at' => now()])),
                ]),
            ]);
    }
}
