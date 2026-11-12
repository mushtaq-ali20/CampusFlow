<?php

namespace App\Filament\Resources\Complaints\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * The history of the complaint: assignments, updates, escalations and the resolution, newest first.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('History');
    }

    #[On('refreshRelationManager')]
    public function refreshHistory(): void
    {
        $this->resetTable();
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
                    ->label(__('Update'))
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label(__('By'))
                    ->placeholder(__('Automatic')),
            ]);
    }
}
