<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\Paymentmethod;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('paymentmethod.name')
                    ->label(__('Payment Method')),
                TextColumn::make('value')
                    ->label(__('Amount'))
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
            Select::make('payment_method')
                ->label(__('Payment Method'))
                ->options(Paymentmethod::pluck('name', 'code'))
                ->searchable()
                ->preload(),
            TextInput::make('value')
                ->label(__('Amount'))
                ->numeric()
                ->required()
                ->step(0.01)
                ->prefix(config('campusflow.currency_position') === 'before' ? config('campusflow.currency_symbol') : null)
                ->suffix(config('campusflow.currency_position') === 'after' ? config('campusflow.currency_symbol') : null),
            DatePicker::make('date')
                ->label(__('Date'))
                ->default(now()),
            TextInput::make('comment')
                ->label(__('Comment'))
                ->maxLength(255),
        ]);
    }
}
