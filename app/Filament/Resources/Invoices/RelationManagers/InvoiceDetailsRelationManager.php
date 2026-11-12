<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceDetails';

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_name')
                    ->label(__('Product')),
                TextColumn::make('product_code')
                    ->label(__('Code'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label(__('Qty')),
                TextColumn::make('price')
                    ->label(__('Unit Price'))
                    ->money(config('campusflow.currency_code', 'USD')),
                TextColumn::make('comment')
                    ->label(__('Comment'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('product_name')
                ->label(__('Product'))
                ->required()
                ->maxLength(255),
            TextInput::make('product_code')
                ->label(__('Code'))
                ->maxLength(255),
            TextInput::make('quantity')
                ->label(__('Qty'))
                ->numeric()
                ->default(1)
                ->minValue(0.01)
                ->step(0.01)
                ->required(),
            TextInput::make('price')
                ->label(__('Unit Price'))
                ->numeric()
                ->required()
                ->step(0.01)
                ->prefix(config('campusflow.currency_position') === 'before' ? config('campusflow.currency_symbol') : null)
                ->suffix(config('campusflow.currency_position') === 'after' ? config('campusflow.currency_symbol') : null),
            TextInput::make('comment')
                ->label(__('Comment'))
                ->maxLength(255),
        ]);
    }
}
