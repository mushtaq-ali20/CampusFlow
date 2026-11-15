<?php

namespace App\Filament\Resources\FeeVouchers\Pages;

use App\Enums\VoucherStatus;
use App\Filament\Actions\CollectPaymentAction;
use App\Filament\Concerns\ShowsRecordHeader;
use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Models\FeeVoucher;
use App\Services\FeeAccountService;
use App\Services\FeeService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewFeeVoucher extends ViewRecord
{
    protected static string $resource = FeeVoucherResource::class;

    use ShowsRecordHeader;

    protected function recordHeader(): array
    {
        $voucher = $this->voucher();
        $voucher->loadMissing('admission.student', 'admission.section');

        return [
            'title' => $voucher->voucher_number,
            'subtitle' => collect([$voucher->admission?->student?->name, $voucher->admission?->section?->full_name, $voucher->month?->translatedFormat('F Y'), FeeService::money($voucher->balance).' '.__('due')])->filter()->implode(' · '),
            'badge' => ['label' => $voucher->status->getLabel(), 'color' => $voucher->status->getColor()],
        ];
    }

    protected function voucher(): FeeVoucher
    {
        /** @var FeeVoucher $voucher */
        $voucher = $this->getRecord();

        return $voucher;
    }

    protected function canCollect(): bool
    {
        return auth()->user()?->can('fees.collect') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            CollectPaymentAction::make(),
            ActionGroup::make([
                $this->installmentPlanAction(),
                $this->adjustmentAction(),
            ]),
        ];
    }

    /**
     * Splits the balance of the voucher into parts with their own due dates.
     */
    protected function installmentPlanAction(): Action
    {
        return Action::make('installmentPlan')
            ->label(fn (): string => $this->voucher()->installments()->exists() ? __('Change installment plan') : __('Installment plan'))
            ->icon(Heroicon::OutlinedQueueList)
            ->visible(fn (): bool => $this->canCollect() && $this->voucher()->status !== VoucherStatus::Paid)
            ->modalDescription(fn (): string => __('Split the balance of :balance into parts. Each part must be due after the one before.', ['balance' => FeeService::money($this->voucher()->balance)]))
            ->fillForm(function (): array {
                $existing = $this->voucher()->installments()->get();

                return ['parts' => $existing->isNotEmpty()
                    ? $existing->map(fn ($part): array => ['amount' => $part->amount, 'due_date' => $part->due_date->toDateString()])->all()
                    : [['amount' => null, 'due_date' => null], ['amount' => null, 'due_date' => null]]];
            })
            ->schema([
                Repeater::make('parts')
                    ->label(__('Installments'))
                    ->schema([
                        TextInput::make('amount')->label(__('Amount'))->integer()->minValue(1)->required(),
                        DatePicker::make('due_date')->label(__('Due date'))->required(),
                    ])
                    ->columns(2)
                    ->minItems(2)
                    ->maxItems(FeeAccountService::MAX_INSTALLMENTS)
                    ->reorderable(false)
                    ->addActionLabel(__('Add installment')),
            ])
            ->action(function (array $data): void {
                try {
                    app(FeeAccountService::class)->planInstallments($this->voucher(), array_values($data['parts']));
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                $this->refreshFormData(['due_date']);
                Notification::make()->title(__('Installment plan saved'))->success()->send();
            });
    }

    /**
     * Asks to charge more on the voucher or to reduce it. Staff who manage the fees approve it.
     */
    protected function adjustmentAction(): Action
    {
        return Action::make('requestAdjustment')
            ->label(__('Request adjustment'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => $this->canCollect() && $this->voucher()->status !== VoucherStatus::Paid)
            ->modalDescription(__('Use a positive amount to charge more and a negative amount to reduce the voucher. It waits for the approval of staff who manage the fees.'))
            ->schema([
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->integer()
                    ->rules(['not_in:0'])
                    ->required(),
                TextInput::make('reason')
                    ->label(__('Reason'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                try {
                    app(FeeAccountService::class)->requestAdjustment($this->voucher(), (int) $data['amount'], $data['reason'], auth()->user());
                } catch (DomainException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('Adjustment requested'))->body(__('It waits for approval.'))->success()->send();
            });
    }
}
