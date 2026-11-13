<?php

namespace App\Filament\Resources\FeePayments;

use App\Enums\PaymentMethod;
use App\Filament\Actions\RequestRefundAction;
use App\Filament\Resources\FeePayments\Pages\ListFeePayments;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\FeePayment;
use App\Services\FeeService;
use App\Services\ReceiptService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The payments collected, with their receipts. Branch staff see the payments of their branch only.
 */
class FeePaymentResource extends Resource
{
    protected static ?string $model = FeePayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Fees');
    }

    public static function getModelLabel(): string
    {
        return __('Payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payments and receipts');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['voucher.admission.student', 'campus', 'collector']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('receipt_number')
                    ->label(__('Receipt No.'))
                    ->searchable(),
                TextColumn::make('voucher.admission.student.name')
                    ->label(__('Student'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('voucher.admission.student.user', fn (Builder $users) => $users
                        ->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('voucher.month')
                    ->label(__('Fee month'))
                    ->date('M Y'),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('Payment method'))
                    ->badge(),
                TextColumn::make('paid_on')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('campus.name')
                    ->label(__('Branch')),
                TextColumn::make('collector.name')
                    ->label(__('Collected by')),
            ])
            ->filters([
                SelectFilter::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => SectionResource::branchOptions()),
                SelectFilter::make('method')
                    ->label(__('Payment method'))
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                Action::make('downloadReceipt')
                    ->label(__('Receipt'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->action(fn (FeePayment $record) => app(ReceiptService::class)->download($record)),
                RequestRefundAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeePayments::route('/'),
        ];
    }
}
