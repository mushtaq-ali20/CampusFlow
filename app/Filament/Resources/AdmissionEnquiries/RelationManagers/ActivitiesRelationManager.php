<?php

namespace App\Filament\Resources\AdmissionEnquiries\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The history of the enquiry: follow-ups and admission steps, newest first.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('History');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime(),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label(__('By'))
                    ->placeholder('—'),
            ]);
    }
}
