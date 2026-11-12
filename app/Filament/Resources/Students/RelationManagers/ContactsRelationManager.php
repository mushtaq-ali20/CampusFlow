<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('firstname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('lastname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('idnumber')
                    ->label(__('ID Number'))
                    ->nullable(),
                TextInput::make('email')
                    ->email()
                    ->nullable(),
                TextInput::make('address')
                    ->nullable(),
                Select::make('relationship_id')
                    ->relationship('relationship', 'name')
                    ->preload()
                    ->nullable(),
                Select::make('profession_id')
                    ->relationship('profession', 'name')
                    ->preload()
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('firstname')
                    ->searchable(),
                TextColumn::make('lastname')
                    ->searchable(),
                TextColumn::make('email'),
                TextColumn::make('relationship.name')
                    ->label(__('Relationship')),
                TextColumn::make('phone.phone_number')
                    ->label(__('Phone'))
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
