<?php

namespace App\Filament\Resources\FeeVouchers\RelationManagers;

use App\Models\FeeVoucher;
use App\Services\FeeAccountService;
use App\Services\FeeService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The installment plan of the voucher, with what is paid on each part.
 */
class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->installments()->exists();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Installments');
    }

    public function table(Table $table): Table
    {
        /** @var FeeVoucher $voucher */
        $voucher = $this->getOwnerRecord();
        $status = app(FeeAccountService::class)->installmentStatus($voucher)->keyBy(fn (array $part): int => $part['installment']->id);

        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('sequence')->label('#'),
                TextColumn::make('due_date')->label(__('Due date'))->date(),
                TextColumn::make('amount')->label(__('Amount'))->formatStateUsing(fn (int $state): string => FeeService::money($state))->alignEnd(),
                TextColumn::make('paid')
                    ->label(__('Paid'))
                    ->state(fn ($record): string => FeeService::money($status[$record->id]['paid'] ?? 0))
                    ->alignEnd(),
                TextColumn::make('state')
                    ->label(__('Status'))
                    ->state(fn ($record): string => ($status[$record->id]['balance'] ?? $record->amount) === 0 ? __('Paid') : ($record->due_date->isPast() ? __('Overdue') : __('Upcoming')))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        __('Paid') => 'success',
                        __('Overdue') => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}
