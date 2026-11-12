<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The admissions of the student, one for each academic year. They are created from the Admissions screen.
 */
class AdmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'admissions';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Admissions');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user())->with(['campus', 'schoolClass', 'section', 'year'])->latest('id'))
            ->columns([
                TextColumn::make('admission_number')
                    ->label(__('Admission No.')),
                TextColumn::make('year.name')
                    ->label(__('Academic Year')),
                TextColumn::make('campus.name')
                    ->label(__('Branch')),
                TextColumn::make('schoolClass.name')
                    ->label(__('Class')),
                TextColumn::make('section.name')
                    ->label(__('Section')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ]);
    }
}
