<?php

namespace App\Filament\Resources\Admissions\RelationManagers;

use App\Enums\ExamType;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Admission;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The marks of the student in the subjects of the class. A student who passes all the subjects can be promoted.
 */
class ResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'results';

    public function isReadOnly(): bool
    {
        return ! AdmissionResource::canEdit($this->getOwnerRecord());
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Results');
    }

    public function form(Schema $schema): Schema
    {
        /** @var Admission $admission */
        $admission = $this->getOwnerRecord();

        return $schema
            ->components([
                Select::make('exam')
                    ->label(__('Exam'))
                    ->options(ExamType::class)
                    ->default(ExamType::Annual)
                    ->required()
                    ->live(),
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->options(fn (): array => $admission->schoolClass->subjects->pluck('name', 'id')->all())
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('admission_id', $admission->id)->where('exam', $get('exam')),
                    )
                    ->validationMessages(['unique' => __('The marks of this subject are already entered for this exam.')]),
                TextInput::make('obtained_marks')
                    ->label(__('Obtained marks'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (Get $get): ?int => $admission->schoolClass->subjects->firstWhere('id', (int) $get('subject_id'))?->pivot->total_marks)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var Admission $admission */
        $admission = $this->getOwnerRecord();
        $subjects = $admission->schoolClass->subjects->keyBy('id');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('subject')->orderBy('exam')->orderBy('subject_id'))
            ->defaultGroup(Group::make('exam')->label(__('Exam'))->getTitleFromRecordUsing(fn ($record): string => $record->exam->getLabel()))
            ->filters([
                SelectFilter::make('exam')
                    ->label(__('Exam'))
                    ->options(ExamType::class),
            ])
            ->columns([
                TextColumn::make('exam')
                    ->label(__('Exam'))
                    ->badge(),
                TextColumn::make('subject.name')
                    ->label(__('Subject')),
                TextColumn::make('obtained_marks')
                    ->label(__('Obtained marks')),
                TextColumn::make('total')
                    ->label(__('Total marks'))
                    ->state(fn ($record): ?int => $subjects[$record->subject_id]?->pivot->total_marks),
                TextColumn::make('outcome')
                    ->label(__('Result'))
                    ->state(fn ($record): string => $record->obtained_marks >= ($subjects[$record->subject_id]?->pivot->passing_marks ?? 0) ? __('Pass') : __('Fail'))
                    ->badge()
                    ->color(fn (string $state): string => $state === __('Pass') ? 'success' : 'danger'),
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
