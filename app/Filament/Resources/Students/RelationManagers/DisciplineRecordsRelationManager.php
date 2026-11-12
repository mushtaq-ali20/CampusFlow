<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The merits and violations of the student, recorded from the Guidance & violation screen.
 */
class DisciplineRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'disciplineRecords';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('discipline.view') ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Discipline');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user())->with(['category', 'recordedBy', 'section.schoolClass']))
            ->defaultSort('discipline_records.date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date(),
                TextColumn::make('section.full_name')
                    ->label(__('Section')),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->description(fn ($record): ?string => $record->remarks)
                    ->wrap(),
                TextColumn::make('points')
                    ->label(__('Points'))
                    ->formatStateUsing(fn (int $state): string => sprintf('%+d', $state))
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->alignEnd()
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('consequence')
                    ->label(__('What follows'))
                    ->badge()
                    ->placeholder(__('Merit')),
                TextColumn::make('recordedBy.name')
                    ->label(__('Recorded by')),
            ]);
    }
}
