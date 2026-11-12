<?php

namespace App\Filament\Resources\Appraisals\Pages;

use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Models\AppraisalCycle;
use App\Models\EmployeeProfile;
use App\Services\AppraisalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAppraisals extends ManageRecords
{
    protected static string $resource = AppraisalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label(__('Start an evaluation'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => AppraisalResource::manages())
                ->modalDescription(__('To start the evaluations of every employee at once, use Start evaluations on the evaluation cycle.'))
                ->schema([
                    Select::make('appraisal_cycle_id')
                        ->label(__('Cycle'))
                        ->options(fn (): array => AppraisalCycle::query()->where('is_closed', false)->orderByDesc('from_date')->pluck('name', 'id')->all())
                        ->required(),
                    Select::make('employee_profile_id')
                        ->label(__('Employee'))
                        ->options(fn (): array => EmployeeProfile::query()
                            ->visibleTo(auth()->user())
                            ->working()
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn (EmployeeProfile $employee): array => [$employee->id => $employee->user->name.' · '.$employee->designation])
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (array $data) => AppraisalResource::attempt(
                    fn () => app(AppraisalService::class)->start(
                        AppraisalCycle::query()->findOrFail($data['appraisal_cycle_id']),
                        EmployeeProfile::query()->visibleTo(auth()->user())->findOrFail($data['employee_profile_id']),
                        auth()->user(),
                    ),
                    __('Evaluation started'),
                )),
        ];
    }
}
