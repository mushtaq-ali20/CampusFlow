<?php

namespace App\Filament\Resources\LeaveAllowances;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\LeaveAllowances\Pages\ManageLeaveAllowances;
use App\Models\LeaveAllowance;
use App\Models\LeaveType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The days a year each leave type allows, and whether the leave is paid. Unpaid leave comes off the salary.
 */
class LeaveAllowanceResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'employees.manage';

    protected static string $deletePermission = 'employees.manage';

    protected static ?string $model = LeaveAllowance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 535;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('employees.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Leave allowance');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Leave allowances');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('leave_type_id')
                    ->label(__('Leave Type'))
                    ->options(fn (): array => LeaveType::query()->get()->pluck('name', 'id')->all())
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText(__('The leave types are kept under Leave Types in the settings.')),
                TextInput::make('days_per_year')
                    ->label(__('Days a year'))
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->helperText(__('0 means no limit.')),
                Toggle::make('is_paid')
                    ->label(__('Paid leave'))
                    ->default(true)
                    ->helperText(__('The days of an unpaid leave are deducted from the salary.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('leaveType'))
            ->paginated(false)
            ->columns([
                TextColumn::make('leaveType.name')->label(__('Leave Type'))->weight('bold'),
                TextColumn::make('days_per_year')->label(__('Days a year'))->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : __('No limit')),
                IconColumn::make('is_paid')->label(__('Paid'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveAllowances::route('/'),
        ];
    }
}
