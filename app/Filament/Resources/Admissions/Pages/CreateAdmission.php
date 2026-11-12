<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmission extends CreateRecord
{
    protected static string $resource = AdmissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AdmissionResource::alignWithSection($data);
    }
}
