<?php

namespace App\Filament\Resources\JobApplicants\Pages;

use App\Enums\ApplicantStage;
use App\Filament\Resources\JobApplicants\JobApplicantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJobApplicants extends ManageRecords
{
    protected static string $resource = JobApplicantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Enter application'))
                ->mutateDataUsing(fn (array $data): array => [...$data, 'stage' => ApplicantStage::Applied, 'recorded_by' => auth()->id()]),
        ];
    }
}
