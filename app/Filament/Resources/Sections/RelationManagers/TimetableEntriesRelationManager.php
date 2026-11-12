<?php

namespace App\Filament\Resources\Sections\RelationManagers;

use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * The weekly timetable of the section: the subject and teacher of each period of each weekday.
 * A teacher cannot be in two sections in the same period.
 */
class TimetableEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timetableEntries';

    public function isReadOnly(): bool
    {
        return ! SectionResource::canEdit($this->getOwnerRecord());
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Timetable');
    }

    /**
     * @return array<int, string>
     */
    public static function dayOptions(): array
    {
        return collect(TimetableEntry::DAYS)->map(fn (string $day): string => __($day))->all();
    }

    public function form(Schema $schema): Schema
    {
        /** @var Section $section */
        $section = $this->getOwnerRecord();

        return $schema
            ->components([
                Select::make('day_of_week')
                    ->label(__('Day'))
                    ->options(static::dayOptions())
                    ->required()
                    ->live(),
                Select::make('timetable_period_id')
                    ->label(__('Period'))
                    ->options(fn (): array => TimetablePeriod::query()->where('is_break', false)->ordered()->get()->mapWithKeys(fn (TimetablePeriod $period): array => [$period->id => $period->name.' ('.$period->timeRange().')'])->all())
                    ->required()
                    ->live()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('section_id', $section->id)->where('day_of_week', $get('day_of_week')),
                    )
                    ->validationMessages(['unique' => __('This period already has a subject on this day.')]),
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->options(fn (): array => $section->schoolClass->subjects()->pluck('subjects.name', 'subjects.id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) use ($section): void {
                        $set('teacher_id', $section->teachingAssignments()->where('subject_id', $state)->value('teacher_id'));
                    }),
                Select::make('teacher_id')
                    ->label(__('Teacher'))
                    ->options(fn (): array => SectionResource::teacherOptions())
                    ->searchable()
                    ->required()
                    ->rules(fn (Get $get, ?Model $record): array => [static::teacherIsFree($get, $record)]),
            ]);
    }

    /**
     * Validates that the teacher has no other section in the period of the day.
     */
    protected static function teacherIsFree(Get $get, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
            $busy = TimetableEntry::query()
                ->where('teacher_id', $value)
                ->where('timetable_period_id', $get('timetable_period_id'))
                ->where('day_of_week', $get('day_of_week'))
                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($busy) {
                $fail(__('This teacher already teaches another section in this period.'));
            }
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['period', 'subject', 'teacher'])->orderBy('day_of_week'))
            ->defaultGroup('day_of_week')
            ->columns([
                TextColumn::make('day_of_week')
                    ->label(__('Day'))
                    ->formatStateUsing(fn (int $state): string => __(TimetableEntry::DAYS[$state] ?? '')),
                TextColumn::make('period.name')
                    ->label(__('Period')),
                TextColumn::make('time')
                    ->label(__('Time'))
                    ->state(fn (TimetableEntry $record): string => $record->period->timeRange()),
                TextColumn::make('subject.name')
                    ->label(__('Subject')),
                TextColumn::make('teacher.name')
                    ->label(__('Teacher')),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
