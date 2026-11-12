<?php

namespace App\Filament\Resources\VendorBills;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\VendorBills\Pages\ManageVendorBills;
use App\Models\Campus;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Services\FeeService;
use App\Services\PayableService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The bills of the vendors and their payments. A bill is paid in full or in parts; a bill entered by mistake is
 * cancelled as long as nothing was paid on it.
 */
class VendorBillResource extends Resource
{
    protected static ?string $model = VendorBill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 600;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('payables.view') ?? false;
    }

    public static function manages(): bool
    {
        return auth()->user()?->can('payables.manage') ?? false;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $allowed = match ($action) {
            'viewAny', 'view' => static::canAccess(),
            'create' => static::manages(),
            default => false,
        };

        return $allowed ? parent::getAuthorizationResponse($action, $record) : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Accounts');
    }

    public static function getModelLabel(): string
    {
        return __('Vendor bill');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vendor bills');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->outstanding()->whereDate('due_date', '<', now())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vendor_id')
                    ->label(__('Vendor'))
                    ->options(fn (): array => Vendor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('campus_id')
                    ->label(__('Branch'))
                    ->options(function (): array {
                        $own = auth()->user()?->campus_id;

                        return Campus::query()->when($own, fn (Builder $query) => $query->whereKey($own))->get()->pluck('name', 'id')->all();
                    })
                    ->default(fn (): ?int => auth()->user()?->activeBranchId())
                    ->required(),
                Select::make('expense_category_id')
                    ->label(__('Expense category'))
                    ->options(fn (): array => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                TextInput::make('bill_number')->label(__('Bill No. of the vendor'))->maxLength(50),
                DatePicker::make('bill_date')->label(__('Bill date'))->default(now())->maxDate(now())->required(),
                DatePicker::make('due_date')->label(__('Due date'))->default(now()->addDays(30))->required()->afterOrEqual('bill_date'),
                TextInput::make('amount')->label(__('Amount'))->integer()->minValue(1)->required()->prefix(config('campusflow.currency_symbol')),
                TextInput::make('description')->label(__('Details'))->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vendor', 'category', 'campus', 'payments']))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('reference')->label(__('Bill'))->description(fn (VendorBill $record): ?string => $record->bill_number)->searchable(['reference', 'bill_number']),
                TextColumn::make('vendor.name')->label(__('Vendor'))->description(fn (VendorBill $record): ?string => $record->description)->searchable(),
                TextColumn::make('category.name')->label(__('Category'))->placeholder('—')->toggleable(),
                TextColumn::make('campus.name')->label(__('Branch'))->toggleable(),
                TextColumn::make('due_date')
                    ->label(__('Due'))
                    ->date('d M Y')
                    ->description(fn (VendorBill $record): string => __('Bill of :date', ['date' => $record->bill_date->format('d M Y')]))
                    ->color(fn (VendorBill $record): ?string => $record->balance > 0 && $record->due_date->lt(now()->startOfDay()) ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('amount')->label(__('Amount'))->formatStateUsing(fn (int $state): string => FeeService::money($state))->alignEnd(),
                TextColumn::make('balance')
                    ->label(__('Owed'))
                    ->state(fn (VendorBill $record): int => $record->balance)
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->description(fn (VendorBill $record): ?string => $record->payments->isNotEmpty()
                        ? $record->payments->map(fn (VendorPayment $payment): string => $payment->voucher_number.' · '.$payment->paid_on->format('d M').' · '.FeeService::money($payment->amount))->implode(', ')
                        : null)
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(BillStatus::class),
                SelectFilter::make('vendor_id')->label(__('Vendor'))->options(fn (): array => Vendor::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                SelectFilter::make('expense_category_id')->label(__('Expense category'))->options(fn (): array => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('overdue')->label(__('Overdue'))->query(fn (Builder $query) => $query->outstanding()->whereDate('due_date', '<', now())),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label(__('Pay'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (VendorBill $record): bool => $record->balance > 0 && static::manages())
                    ->modalHeading(fn (VendorBill $record): string => __('Pay :vendor', ['vendor' => $record->vendor->name]))
                    ->modalDescription(fn (VendorBill $record): string => __(':amount is owed on bill :reference.', ['amount' => FeeService::money($record->balance), 'reference' => $record->reference]))
                    ->fillForm(fn (VendorBill $record): array => ['amount' => $record->balance, 'method' => PaymentMethod::BankTransfer, 'paid_on' => now()])
                    ->schema(fn (VendorBill $record): array => [
                        TextInput::make('amount')->label(__('Amount'))->integer()->minValue(1)->maxValue($record->balance)->required(),
                        Select::make('method')->label(__('Paid by'))->options(PaymentMethod::class)->required(),
                        DatePicker::make('paid_on')->label(__('Paid on'))->maxDate(now())->required(),
                        TextInput::make('reference')->label(__('Cheque or transfer No.'))->maxLength(100),
                    ])
                    ->action(function (VendorBill $record, array $data): void {
                        $method = $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']);

                        try {
                            $payment = app(PayableService::class)->pay($record, (int) $data['amount'], $method, now()->parse($data['paid_on']), auth()->user(), $data['reference'] ?? null);
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Payment recorded'))->body(__('Payment voucher :number', ['number' => $payment->voucher_number]))->success()->send();
                    }),
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (VendorBill $record): bool => $record->status === BillStatus::Unpaid && static::manages())
                    ->requiresConfirmation()
                    ->modalDescription(__('Use this for a bill entered by mistake. It stays in the list as cancelled.'))
                    ->action(function (VendorBill $record): void {
                        try {
                            app(PayableService::class)->cancel($record);
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendorBills::route('/'),
        ];
    }
}
