<?php

namespace App\Filament\Actions;

use App\Enums\PaymentMethod;
use App\Enums\VoucherStatus;
use App\Models\FeeVoucher;
use App\Services\FeeService;
use App\Services\ReceiptService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Collects a full or partial payment for a fee voucher, then downloads the receipt.
 * Branch staff can only collect the fees of their own branch (the vouchers they see).
 */
class CollectPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'collectPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Collect payment'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (?FeeVoucher $record): bool => $record !== null
                && $record->status !== VoucherStatus::Paid
                && (auth()->user()?->can('fees.collect') ?? false)
                && ($record->campus_id === auth()->user()?->campus_id || auth()->user()?->campus_id === null))
            ->modalHeading(fn (FeeVoucher $record): string => __('Collect payment from :student', ['student' => $record->admission->student->name]))
            ->modalDescription(fn (FeeVoucher $record): string => __('Balance: :balance', ['balance' => FeeService::money($record->balance)]))
            ->schema(fn (FeeVoucher $record): array => [
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue($record->balance)
                    ->default($record->balance)
                    ->required(),
                Select::make('method')
                    ->label(__('Payment method'))
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
                    ->required(),
                DatePicker::make('paid_on')
                    ->label(__('Payment date'))
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                TextInput::make('reference')
                    ->label(__('Reference (cheque or transaction number)'))
                    ->maxLength(255),
            ])
            ->action(function (FeeVoucher $record, array $data) {
                try {
                    $payment = app(FeeService::class)->recordPayment(
                        $record,
                        (int) $data['amount'],
                        PaymentMethod::from($data['method']),
                        Carbon::parse($data['paid_on']),
                        $data['reference'] ?? null,
                        auth()->user(),
                    );
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return null;
                }

                Notification::make()
                    ->title(__('Payment collected. Receipt :number', ['number' => $payment->receipt_number]))
                    ->success()
                    ->send();

                return app(ReceiptService::class)->download($payment);
            });
    }
}
