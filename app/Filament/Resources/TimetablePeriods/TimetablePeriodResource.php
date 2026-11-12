<?php

namespace App\Filament\Resources\TimetablePeriods;

use App\Enums\Shift;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\TimetablePeriods\Pages\ManageTimetablePeriods;
use App\Models\TimetablePeriod;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The periods of the school day. Administrators configure them on demand, and the timetables use them.
 */
class TimetablePeriodResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'school.manage';

    protected static string $deletePermission = 'school.manage';

    protected static ?string $model = TimetablePeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 40;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('school.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getModelLabel(): string
    {
        return __('Timetable period');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Timetable periods');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->placeholder('Period 1')
                    ->required()
                    ->maxLength(255),
                Select::make('shift')
                    ->label(__('Shift'))
                    ->options(Shift::class)
                    ->default(Shift::Morning)
                    ->required(),
                TimePicker::make('start_time')
                    ->label(__('Start time'))
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label(__('End time'))
                    ->seconds(false)
                    ->after('start_time')
                    ->required(),
                TextInput::make('order')
                    ->label(__('Order'))
                    ->integer()
                    ->minValue(0)
                    ->default(fn (): int => (int) TimetablePeriod::query()->max('order') + 1)
                    ->required(),
                Toggle::make('is_break')
                    ->label(__('Break (no lesson)')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                TextColumn::make('order')
                    ->label(__('Order'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('shift')
                    ->label(__('Shift'))
                    ->badge(),
                TextColumn::make('time')
                    ->label(__('Time'))
                    ->state(fn (TimetablePeriod $record): string => $record->timeRange()),
                IconColumn::make('is_break')
                    ->label(__('Break'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('shift')
                    ->label(__('Shift'))
                    ->options(Shift::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ManageTimetablePeriods::route('/'),
        ];
    }
}
