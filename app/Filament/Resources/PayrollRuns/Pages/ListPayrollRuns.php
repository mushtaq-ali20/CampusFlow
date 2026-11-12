<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Models\Campus;
use App\Services\PayrollService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('Make payroll'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => auth()->user()?->can('payroll.manage') ?? false)
                ->modalDescription(__('Makes a draft with a salary slip for every employee of the branch: the basic salary, the fixed allowances and deductions of the employee file, and a deduction for the approved days of unpaid leave. You can adjust the slips before finalizing.'))
                ->schema([
                    Select::make('campus_id')
                        ->label(__('Branch'))
                        ->options(function (): array {
                            $own = auth()->user()?->campus_id;

                            return Campus::query()->when($own, fn (Builder $query) => $query->whereKey($own))->get()->pluck('name', 'id')->all();
                        })
                        ->default(fn (): ?int => auth()->user()?->activeBranchId())
                        ->required(),
                    Select::make('month')
                        ->label(__('Month'))
                        ->options(fn (): array => collect(range(0, 11))
                            ->mapWithKeys(fn (int $back): array => [now()->startOfMonth()->subMonths($back)->format('Y-m') => now()->startOfMonth()->subMonths($back)->translatedFormat('F Y')])
                            ->all())
                        ->default(now()->format('Y-m'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $run = app(PayrollService::class)->generate(Campus::query()->findOrFail($data['campus_id']), $data['month'], auth()->user());
                    } catch (DomainException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('Payroll made'))->body(__('Check the slips, then finalize it.'))->success()->send();

                    return redirect(PayrollRunResource::getUrl('view', ['record' => $run]));
                }),
        ];
    }
}
