<?php

namespace App\Filament\Resources\SchoolClasses\RelationManagers;

use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The subjects studied in a class, with the marks a student needs to pass each of them.
 */
class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public function isReadOnly(): bool
    {
        return ! SchoolClassResource::canEdit($this->getOwnerRecord());
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Subjects');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::marksFields());
    }

    /**
     * @return list<TextInput>
     */
    protected static function marksFields(): array
    {
        return [
            TextInput::make('total_marks')
                ->label(__('Total marks'))
                ->integer()
                ->minValue(1)
                ->default(100)
                ->required(),
            TextInput::make('passing_marks')
                ->label(__('Passing marks'))
                ->integer()
                ->minValue(0)
                ->lte('total_marks')
                ->default(33)
                ->required(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Subject'))
                    ->searchable(),
                TextColumn::make('code')
                    ->label(__('Code')),
                TextColumn::make('total_marks')
                    ->label(__('Total marks')),
                TextColumn::make('passing_marks')
                    ->label(__('Passing marks')),
            ])
            ->headerActions([
                AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...static::marksFields(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ]);
    }
}
