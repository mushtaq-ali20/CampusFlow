<?php

namespace App\Filament\Resources\FeeRefunds;

use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Filament\Resources\FeeRefunds\Pages\ListFeeRefunds;
use App\Models\FeeRefund;
use App\Services\FeeAccountService;
use App\Services\FeeService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The refunds requested from payments. Staff who manage the fees approve or reject them, and record the date the
 * money was given back. Refunds are requested from the payments.
 */
class FeeRefundResource extends Resource
{
    protected static ?string $model = FeeRefund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?int $navigationSort = 65;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
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
        return __('Fees');
    }

    public static function getModelLabel(): string
    {
        return __('Refund');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Refunds');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', RequestStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payment.voucher.admission.student', 'requestedBy', 'decidedBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Requested'))->date()->sortable(),
                TextColumn::make('payment.voucher.admission.student.name')
                    ->label(__('Student'))
                    ->description(fn (FeeRefund $record): ?string => $record->payment->receipt_number),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->alignEnd(),
                TextColumn::make('method')->label(__('Given back by'))->badge(),
                TextColumn::make('reason')->label(__('Reason'))->wrap(),
                TextColumn::make('requestedBy.name')->label(__('Requested by')),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->description(fn (FeeRefund $record): ?string => $record->refunded_on ? __('Given back on :date', ['date' => $record->refunded_on->format('d M Y')]) : null),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(RequestStatus::class)->default(RequestStatus::Pending->value),
                SelectFilter::make('method')->label(__('Given back by'))->options(PaymentMethod::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (FeeRefund $record): bool => $record->status === RequestStatus::Pending && (auth()->user()?->can('fees.approve') ?? false))
                    ->schema([
                        DatePicker::make('refunded_on')->label(__('Given back on'))->default(now())->maxDate(now())->required(),
                    ])
                    ->action(function (FeeRefund $record, array $data): void {
                        try {
                            app(FeeAccountService::class)->approveRefund($record, auth()->user(), now()->parse($data['refunded_on']));
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Refund approved'))->success()->send();
                    })
                    ->button(),
                ActionGroup::make([
                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->visible(fn (FeeRefund $record): bool => $record->status === RequestStatus::Pending && (auth()->user()?->can('fees.approve') ?? false))
                        ->requiresConfirmation()
                        ->action(fn (FeeRefund $record) => app(FeeAccountService::class)->rejectRefund($record, auth()->user())),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeRefunds::route('/'),
        ];
    }
}
