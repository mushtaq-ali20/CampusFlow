<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Enums\PayrollStatus;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\RelationManagers\SlipsRelationManager;
use App\Models\PayrollRun;
use App\Services\FeeService;
use App\Services\PayrollService;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The monthly payroll of each branch: a draft is made from the employee files and the approved leaves, adjusted,
 * finalized, and then the salaries are paid and the slips printed. Staff who manage the payroll make and change it.
 */
class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 540;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('payroll.view') ?? false;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        return in_array($action, ['viewAny', 'view'], true) && static::canAccess()
            ? parent::getAuthorizationResponse($action, $record)
            : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Payroll');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payroll');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function infolist(Schema $schema): Schema
    {
        $summary = fn (PayrollRun $record): array => app(PayrollService::class)->summary($record);
        $money = fn (int $state): string => FeeService::money($state);

        return $schema
            ->components([
                Section::make()
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('month')->label(__('Month'))->formatStateUsing(fn (PayrollRun $record): string => $record->monthLabel()),
                        TextEntry::make('campus.name')->label(__('Branch')),
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('employees')->label(__('Employees'))->state(fn (PayrollRun $record): int => $summary($record)['employees']),
                        TextEntry::make('basic')->label(__('Basic salaries'))->state(fn (PayrollRun $record): int => $summary($record)['basic'])->formatStateUsing($money),
                        TextEntry::make('allowances')->label(__('Allowances'))->state(fn (PayrollRun $record): int => $summary($record)['allowances'])->formatStateUsing($money),
                        TextEntry::make('deductions')->label(__('Fixed and other deductions'))->state(fn (PayrollRun $record): int => $summary($record)['deductions'])->formatStateUsing($money),
                        TextEntry::make('unpaidLeave')->label(__('Unpaid leave deduction'))->state(fn (PayrollRun $record): int => $summary($record)['unpaidLeave'])->formatStateUsing($money),
                        TextEntry::make('net')->label(__('Net salaries'))->state(fn (PayrollRun $record): int => $summary($record)['net'])->formatStateUsing($money)->weight('bold'),
                        TextEntry::make('paid')->label(__('Paid'))->state(fn (PayrollRun $record): string => $summary($record)['paid'].' / '.$summary($record)['employees']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('campus')->withCount('slips')->withSum('slips', 'net_salary'))
            ->defaultSort('month', 'desc')
            ->columns([
                TextColumn::make('month')->label(__('Month'))->formatStateUsing(fn (PayrollRun $record): string => $record->monthLabel())->sortable(),
                TextColumn::make('campus.name')->label(__('Branch')),
                TextColumn::make('slips_count')->label(__('Employees'))->alignEnd(),
                TextColumn::make('slips_sum_net_salary')->label(__('Net salaries'))->formatStateUsing(fn ($state): string => FeeService::money((int) $state))->alignEnd(),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(PayrollStatus::class),
            ])
            ->recordUrl(fn (PayrollRun $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            SlipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollRuns::route('/'),
            'view' => ViewPayrollRun::route('/{record}'),
        ];
    }
}
