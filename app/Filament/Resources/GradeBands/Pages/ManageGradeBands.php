<?php

namespace App\Filament\Resources\GradeBands\Pages;

use App\Filament\Resources\GradeBands\GradeBandResource;
use App\Services\GradingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageGradeBands extends ManageRecords
{
    protected static string $resource = GradeBandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('formativeWeight')
                ->label(fn (): string => __('Class assessments count for :weight%', ['weight' => app(GradingService::class)->formativeWeight()]))
                ->icon(Heroicon::OutlinedScale)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('school.manage') ?? false)
                ->modalDescription(__('The share of the class assessments of a term in the percentage of each subject. At 0%, only the exam counts. Promotion always needs the passing marks in the annual exam.'))
                ->fillForm(fn (): array => ['weight' => app(GradingService::class)->formativeWeight()])
                ->schema([
                    TextInput::make('weight')
                        ->label(__('Weight of the class assessments (%)'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(50)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(GradingService::class)->setFormativeWeight((int) $data['weight']);
                    Notification::make()->title(__('Weight saved'))->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
