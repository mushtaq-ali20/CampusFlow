<?php

namespace App\Filament\Resources\PayrollRuns\RelationManagers;

use App\Enums\PayItemKind;
use App\Enums\PaymentMethod;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\SalarySlipItem;
use App\Services\FeeService;
use App\Services\PayrollService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The salary slips of a payroll. While the payroll is a draft, one-off lines can be added to a slip; once it is
 * finalized the salaries are paid and the slips printed.
 */
class SlipsRelationManager extends RelationManager
{
    protected static string $relationship = 'slips';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Salary slips');
    }

    protected function canManage(): bool
    {
        return auth()->user()?->can('payroll.manage') ?? false;
    }

    public function table(Table $table): Table
    {
        $money = fn ($state): string => FeeService::money((int) $state);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee.user', 'items']))
            ->defaultSort('slip_number')
            ->paginated(false)
            ->columns([
                TextColumn::make('employee.user.name')
                    ->label(__('Employee'))
                    ->description(fn (SalarySlip $record): string => $record->employee->employee_code.' · '.$record->employee->designation)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('employee.user', fn (Builder $users) => $users->where('firstname', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('basic_salary')->label(__('Basic'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('allowances_total')->label(__('Allowances'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('deductions_total')->label(__('Deductions'))->formatStateUsing($money)->alignEnd(),
                TextColumn::make('unpaid_deduction')
                    ->label(__('Unpaid leave'))
                    ->formatStateUsing($money)
                    ->description(fn (SalarySlip $record): ?string => $record->unpaid_days > 0 ? trans_choice('{1} :count day|[2,*] :count days', $record->unpaid_days, ['count' => $record->unpaid_days]) : null)
                    ->alignEnd(),
                TextColumn::make('net_salary')->label(__('Net salary'))->formatStateUsing($money)->weight('bold')->alignEnd(),
                TextColumn::make('paid_on')
                    ->label(__('Paid'))
                    ->badge()
                    ->formatStateUsing(fn (SalarySlip $record): string => $record->isPaid() ? __('Paid on :date', ['date' => $record->paid_on->format('d M')]) : __('Unpaid'))
                    ->color(fn (SalarySlip $record): string => $record->isPaid() ? 'success' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('paid')
                    ->label(__('Paid'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('paid_on'),
                        false: fn (Builder $query) => $query->whereNull('paid_on'),
                    ),
            ])
            ->recordActions([
                Action::make('slip')
                    ->label(__('Slip'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (SalarySlip $record): StreamedResponse => app(PayrollService::class)->slipsPdf($record->run, $record)),
                Action::make('markPaid')
                    ->label(__('Pay'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (SalarySlip $record): bool => ! $record->isPaid() && ! $this->payroll()->isDraft() && $this->canManage())
                    ->schema([
                        Select::make('method')->label(__('Paid by'))->options(PaymentMethod::class)->default(PaymentMethod::BankTransfer)->required(),
                        DatePicker::make('paid_on')->label(__('Paid on'))->default(now())->maxDate(now())->required(),
                    ])
                    ->action(function (SalarySlip $record, array $data): void {
                        $method = $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']);

                        $this->attempt(fn () => app(PayrollService::class)->markPaid($record, $method, now()->parse($data['paid_on']), auth()->user()), __('Salary paid'));
                    }),
                ActionGroup::make([
                    Action::make('addLine')
                        ->label(__('Add a line'))
                        ->icon(Heroicon::OutlinedPlus)
                        ->schema([
                            Select::make('kind')->label(__('Type'))->options(PayItemKind::class)->required(),
                            TextInput::make('name')->label(__('Name'))->placeholder(__('Bonus, Damaged item'))->required()->maxLength(255),
                            TextInput::make('amount')->label(__('Amount'))->integer()->minValue(1)->required(),
                        ])
                        ->action(function (SalarySlip $record, array $data): void {
                            $kind = $data['kind'] instanceof PayItemKind ? $data['kind'] : PayItemKind::from($data['kind']);

                            $this->attempt(fn () => app(PayrollService::class)->addLine($record, $kind, $data['name'], (int) $data['amount']), __('Line added'));
                        }),
                    Action::make('removeLine')
                        ->label(__('Remove a line'))
                        ->icon(Heroicon::OutlinedMinus)
                        ->visible(fn (SalarySlip $record): bool => $record->items->contains('is_manual', true))
                        ->schema(fn (SalarySlip $record): array => [
                            Select::make('item')
                                ->label(__('Line'))
                                ->options($record->items->where('is_manual', true)->mapWithKeys(fn (SalarySlipItem $item): array => [$item->id => $item->name.' · '.FeeService::money($item->amount)])->all())
                                ->required(),
                        ])
                        ->action(function (SalarySlip $record, array $data): void {
                            $item = $record->items()->findOrFail($data['item']);

                            $this->attempt(fn () => app(PayrollService::class)->removeLine($item), __('Line removed'));
                        }),
                ])
                    ->visible(fn (): bool => $this->payroll()->isDraft() && $this->canManage()),
            ]);
    }

    protected function payroll(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = $this->getOwnerRecord();

        return $run;
    }

    private function attempt(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }
}
