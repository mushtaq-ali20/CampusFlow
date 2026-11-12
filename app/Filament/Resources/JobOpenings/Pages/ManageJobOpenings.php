<?php

namespace App\Filament\Resources\JobOpenings\Pages;

use App\Filament\Resources\JobOpenings\JobOpeningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJobOpenings extends ManageRecords
{
    protected static string $resource = JobOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
