<?php

namespace App\Filament\Resources\Admissions\RelationManagers;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The sibling discounts, scholarships and other discounts given to the student. They apply to the vouchers
 * created from then on.
 */
class DiscountsRelationManager extends RelationManager
{
    protected static string $relationship = 'discounts';

    public function isReadOnly(): bool
    {
        return ! AdmissionResource::canEdit($this->getOwnerRecord());
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Discounts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('value')
                    ->label(__('Value'))
                    ->formatStateUsing(fn ($state, $record): string => $record->mode->value === 'percent' ? $state.' %' : number_format($state)),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
