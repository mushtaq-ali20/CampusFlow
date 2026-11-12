<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\EditRecord;

class EditAdmission extends EditRecord
{
    protected static string $resource = AdmissionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AdmissionResource::alignWithSection($data);
    }
}
