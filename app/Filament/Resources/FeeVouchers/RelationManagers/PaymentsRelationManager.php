<?php

namespace App\Filament\Resources\FeeVouchers\RelationManagers;

use App\Filament\Actions\RequestRefundAction;
use App\Models\FeePayment;
use App\Services\FeeService;
use App\Services\ReceiptService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return __('Payments and receipts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('receipt_number')
                    ->label(__('Receipt No.')),
                TextColumn::make('paid_on')
                    ->label(__('Date'))
                    ->date(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => FeeService::money($state)),
                TextColumn::make('method')
                    ->label(__('Payment method'))
                    ->badge(),
                TextColumn::make('reference')
                    ->label(__('Reference')),
                TextColumn::make('collector.name')
                    ->label(__('Collected by')),
            ])
            ->recordActions([
                Action::make('downloadReceipt')
                    ->label(__('Receipt'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->action(fn (FeePayment $record) => app(ReceiptService::class)->download($record)),
                RequestRefundAction::make(),
            ]);
    }
}
