<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\ShowsRecordHeader;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewPayrollRun extends ViewRecord
{
    use ShowsRecordHeader;

    protected static string $resource = PayrollRunResource::class;

    protected function recordHeader(): array
    {
        $run = $this->run();

        return [
            'title' => __('Payroll :month', ['month' => $run->monthLabel()]),
            'subtitle' => collect([$run->campus?->name, trans_choice(':count slip|:count slips', $run->slips()->count())])->filter()->implode(' · '),
            'badge' => ['label' => $run->status->getLabel(), 'color' => $run->status->getColor()],
        ];
    }

    protected function run(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = $this->getRecord();

        return $run;
    }

    protected function canManage(): bool
    {
        return auth()->user()?->can('payroll.manage') ?? false;
    }

    public function getTitle(): string
    {
        return __('Payroll :month', ['month' => $this->run()->monthLabel()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('Finalize'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->visible(fn (): bool => $this->run()->isDraft() && $this->canManage())
                ->requiresConfirmation()
                ->modalDescription(__('After this the slips cannot be changed. The salaries can then be paid.'))
                ->action(fn () => $this->attempt(fn () => app(PayrollService::class)->finalize($this->run()), __('Payroll finalized'))),
            Action::make('payAll')
                ->label(__('Pay all salaries'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (): bool => ! $this->run()->isDraft() && $this->canManage() && $this->run()->slips()->whereNull('paid_on')->exists())
                ->schema([
                    Select::make('method')->label(__('Paid by'))->options(PaymentMethod::class)->default(PaymentMethod::BankTransfer)->required(),
                    DatePicker::make('paid_on')->label(__('Paid on'))->default(now())->maxDate(now())->required(),
                ])
                ->action(function (array $data): void {
                    $method = $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']);

                    $this->attempt(function () use ($method, $data): void {
                        $count = app(PayrollService::class)->markAllPaid($this->run(), $method, now()->parse($data['paid_on']), auth()->user());

                        Notification::make()->title(__(':count salaries paid', ['count' => $count]))->success()->send();
                    });
                }),
            ActionGroup::make([
                Action::make('slips')
                    ->label(__('Download all salary slips'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => app(PayrollService::class)->slipsPdf($this->run())),
                Action::make('download')
                    ->label(__('Download for Excel'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(fn (): StreamedResponse => $this->download()),
                Action::make('discard')
                    ->label(__('Discard draft'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (): bool => $this->run()->isDraft() && $this->canManage())
                    ->requiresConfirmation()
                    ->modalDescription(__('Deletes this draft so that it can be made again, for example after changing an employee file or approving a leave.'))
                    ->action(function () {
                        try {
                            app(PayrollService::class)->discard($this->run());
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        return redirect(PayrollRunResource::getUrl());
                    }),
            ]),
        ];
    }

    public function download(): StreamedResponse
    {
        $rows = app(PayrollService::class)->rows($this->run());
        $name = 'payroll-'.$this->run()->month.'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function attempt(callable $callback, ?string $success = null): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        if ($success !== null) {
            Notification::make()->title($success)->success()->send();
        }

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->run()]));
    }
}
