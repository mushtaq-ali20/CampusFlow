<?php

namespace App\Filament\Resources\EmployeeProfiles\Pages;

use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeProfiles extends ManageRecords
{
    protected static string $resource = EmployeeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->modalWidth('4xl'),
        ];
    }
}
