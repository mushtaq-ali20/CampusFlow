<?php

namespace App\Filament\Resources\FeeVouchers\RelationManagers;

use App\Services\FeeService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Fee details');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('Description')),
                TextColumn::make('kind')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state->value) {
                        'discount' => __('Discount'),
                        'fine' => __('Late fine'),
                        default => __('Fee'),
                    })
                    ->color(fn ($state): string => match ($state->value) {
                        'discount' => 'success',
                        'fine' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state, $record): string => ($record->kind->value === 'discount' ? '- ' : '').FeeService::money($state)),
            ]);
    }
}
