<?php

namespace App\Filament\Resources\FeeAdjustments;

use App\Enums\RequestStatus;
use App\Filament\Resources\FeeAdjustments\Pages\ListFeeAdjustments;
use App\Models\FeeAdjustment;
use App\Services\FeeAccountService;
use App\Services\FeeService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
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
 * The adjustments of fee vouchers requested by the fee staff. Staff who manage the fees approve or reject them;
 * an approved adjustment becomes a fee or a discount line of the voucher. Adjustments are requested from the voucher.
 */
class FeeAdjustmentResource extends Resource
{
    protected static ?string $model = FeeAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static ?int $navigationSort = 60;

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
        return __('Fee adjustment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Fee adjustments');
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['voucher.admission.student', 'requestedBy', 'decidedBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Requested'))->date()->sortable(),
                TextColumn::make('voucher.admission.student.name')
                    ->label(__('Student'))
                    ->description(fn (FeeAdjustment $record): string => $record->voucher->voucher_number),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+ ' : '- ').FeeService::money(abs($state)))
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->alignEnd(),
                TextColumn::make('reason')->label(__('Reason'))->wrap(),
                TextColumn::make('requestedBy.name')->label(__('Requested by')),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->description(fn (FeeAdjustment $record): ?string => $record->decidedBy ? __('by :name', ['name' => $record->decidedBy->name]) : null),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(RequestStatus::class)->default(RequestStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (FeeAdjustment $record): bool => $record->status === RequestStatus::Pending && (auth()->user()?->can('fees.approve') ?? false))
                    ->requiresConfirmation()
                    ->action(function (FeeAdjustment $record): void {
                        try {
                            app(FeeAccountService::class)->approveAdjustment($record, auth()->user());
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(__('Adjustment approved'))->success()->send();
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (FeeAdjustment $record): bool => $record->status === RequestStatus::Pending && (auth()->user()?->can('fees.approve') ?? false))
                    ->requiresConfirmation()
                    ->action(fn (FeeAdjustment $record) => app(FeeAccountService::class)->rejectAdjustment($record, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeAdjustments::route('/'),
        ];
    }
}
