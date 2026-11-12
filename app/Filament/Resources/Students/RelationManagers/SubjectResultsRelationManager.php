<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\ExamType;
use App\Models\SubjectResult;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The exam marks of the student in each subject, with the total and passing marks of the subject in their class.
 */
class SubjectResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjectResults';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('evaluation.view') ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Results');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereHas('admission', fn (Builder $admissions) => $admissions->visibleTo(auth()->user()))
                ->with(['subject', 'admission.year', 'admission.schoolClass.subjects']))
            ->defaultSort('admissions.year_id', 'desc')
            ->columns([
                TextColumn::make('admission.year.name')
                    ->label(__('Academic Year')),
                TextColumn::make('exam')
                    ->label(__('Exam'))
                    ->badge(),
                TextColumn::make('subject.name')
                    ->label(__('Subject')),
                TextColumn::make('obtained_marks')
                    ->label(__('Marks'))
                    ->formatStateUsing(fn (SubjectResult $record): string => self::formatMarks($record->obtained_marks).' / '.(self::marksOf($record, 'total_marks') ?? '—'))
                    ->alignEnd(),
                TextColumn::make('result')
                    ->label(__('Result'))
                    ->state(fn (SubjectResult $record): ?string => self::passed($record) === null ? null : (self::passed($record) ? __('Pass') : __('Fail')))
                    ->badge()
                    ->color(fn (SubjectResult $record): string => self::passed($record) ? 'success' : 'danger')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('exam')
                    ->label(__('Exam'))
                    ->options(ExamType::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('subject_results.exam', $data['value']),
                    )),
            ]);
    }

    private static function marksOf(SubjectResult $record, string $column): ?int
    {
        $subject = $record->admission->schoolClass->subjects->firstWhere('id', $record->subject_id);

        return $subject ? (int) $subject->pivot->{$column} : null;
    }

    private static function passed(SubjectResult $record): ?bool
    {
        $passingMarks = self::marksOf($record, 'passing_marks');

        return $passingMarks === null ? null : $record->obtained_marks >= $passingMarks;
    }

    private static function formatMarks(float $marks): string
    {
        return rtrim(rtrim(number_format($marks, 2, '.', ''), '0'), '.');
    }
}
