<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\VoucherStatus;
use App\Models\FeeVoucher;
use App\Services\FeeService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The fee vouchers of the student, with what was paid and what is still due. Payments are collected from the
 * Fee vouchers screen.
 */
class FeeVouchersRelationManager extends RelationManager
{
    protected static string $relationship = 'feeVouchers';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Fees');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->defaultSort('fee_vouchers.month', 'desc')
            ->columns([
                TextColumn::make('voucher_number')
                    ->label(__('Voucher No.')),
                TextColumn::make('month')
                    ->label(__('Month'))
                    ->date('F Y'),
                TextColumn::make('due_date')
                    ->label(__('Due date'))
                    ->date(),
                TextColumn::make('net_amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->alignEnd(),
                TextColumn::make('paid_amount')
                    ->label(__('Paid'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->alignEnd(),
                TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->state(fn (FeeVoucher $record): int => $record->balance)
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state))
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(VoucherStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('fee_vouchers.status', $data['value']),
                    )),
            ]);
    }
}
