<?php

namespace App\Filament\Actions;

use App\Enums\PaymentMethod;
use App\Models\FeePayment;
use App\Services\FeeAccountService;
use App\Services\FeeService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Asks to give back money from a payment. Staff who manage the fees approve the refund from the Refunds screen.
 */
class RequestRefundAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'requestRefund';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Refund'))
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('gray')
            ->visible(fn (?FeePayment $record): bool => $record !== null
                && (auth()->user()?->can('fees.collect') ?? false)
                && app(FeeAccountService::class)->refundable($record) > 0)
            ->modalHeading(fn (FeePayment $record): string => __('Refund from receipt :number', ['number' => $record->receipt_number]))
            ->modalDescription(fn (FeePayment $record): string => __('Up to :amount can be refunded. The refund needs the approval of staff who manage the fees.', ['amount' => FeeService::money(app(FeeAccountService::class)->refundable($record))]))
            ->schema(fn (FeePayment $record): array => [
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(app(FeeAccountService::class)->refundable($record))
                    ->required(),
                Select::make('method')
                    ->label(__('Given back by'))
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
                    ->required(),
                TextInput::make('reason')
                    ->label(__('Reason'))
                    ->placeholder(__('Student left the school'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (FeePayment $record, array $data): void {
                try {
                    $method = $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']);

                    app(FeeAccountService::class)->requestRefund($record, (int) $data['amount'], $data['reason'], $method, auth()->user());
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Refund requested'))->body(__('It waits for approval.'))->success()->send();
            });
    }
}
