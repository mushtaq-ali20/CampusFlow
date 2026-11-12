<?php

namespace App\Filament\Resources\Sections\RelationManagers;

use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The teacher of each subject of a section. Only the subjects of the section's class can be assigned.
 */
class TeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAssignments';

    public function isReadOnly(): bool
    {
        return ! SectionResource::canEdit($this->getOwnerRecord());
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Subject teachers');
    }

    public function form(Schema $schema): Schema
    {
        /** @var Section $section */
        $section = $this->getOwnerRecord();

        return $schema
            ->components([
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->options(fn (): array => $section->schoolClass->subjects()->pluck('subjects.name', 'subjects.id')->all())
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('section_id', $section->id),
                    )
                    ->validationMessages([
                        'unique' => __('This subject already has a teacher in this section.'),
                    ]),
                Select::make('teacher_id')
                    ->label(__('Teacher'))
                    ->options(fn (): array => SectionResource::teacherOptions())
                    ->searchable()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'teacher']))
            ->columns([
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
