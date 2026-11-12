<?php

namespace App\Filament\Resources\Sections\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The students admitted to the section.
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
        return __('Students');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('student'))
            ->columns([
                TextColumn::make('admission_number')
                    ->label(__('Admission No.')),
                TextColumn::make('student.name')
                    ->label(__('Student')),
                TextColumn::make('admitted_on')
                    ->label(__('Admission date'))
                    ->date(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ]);
    }
}
