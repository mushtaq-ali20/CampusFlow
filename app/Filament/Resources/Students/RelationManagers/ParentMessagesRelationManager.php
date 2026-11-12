<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The WhatsApp messages prepared for the parents of the student.
 */
class ParentMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'parentMessages';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('parents.message') ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Messages to parents');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when(auth()->user()?->activeBranchId(), fn (Builder $query, int $branchId) => $query->where(fn (Builder $messages) => $messages
                    ->where('parent_messages.campus_id', $branchId)
                    ->orWhereNull('parent_messages.campus_id')))
                ->with('sentBy'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Prepared on'))
                    ->dateTime(),
                TextColumn::make('recipient_name')
                    ->label(__('Sent to'))
                    ->placeholder(fn ($record): string => '+'.$record->phone),
                TextColumn::make('body')
                    ->label(__('Message'))
                    ->limit(120)
                    ->wrap(),
                TextColumn::make('sentBy.name')
                    ->label(__('Prepared by')),
            ]);
    }
}
