<?php

namespace App\Filament\Resources\Settings\Years;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Concerns\RequiresClusterAccess;
use App\Filament\Resources\Settings\Years\Pages\ManageYears;
use App\Models\Year;
use App\Services\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class YearResource extends Resource
{
    use RequiresClusterAccess;

    protected static ?string $model = Year::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $cluster = SettingsCluster::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Academic');
    }

    public static function getModelLabel(): string
    {
        return __('Academic Year');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Academic Years');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->placeholder('2026-2027')
                    ->required()
                    ->minLength(1)
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                DatePicker::make('start')
                    ->label(__('Start date')),
                DatePicker::make('end')
                    ->label(__('End date'))
                    ->afterOrEqual('start'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start')
                    ->label(__('Start date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end')
                    ->label(__('End date'))
                    ->date()
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label(__('Current'))
                    ->state(fn (Year $record): bool => $record->isCurrent())
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('setCurrent')
                    ->label(__('Set as current'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->hidden(fn (Year $record): bool => $record->isCurrent())
                    ->action(fn (Year $record) => app(Settings::class)->set(['current_academic_year' => $record->id])),
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageYears::route('/'),
        ];
    }
}
