<?php

namespace App\Filament\Resources\Settings\Institutions\Pages;

use App\Filament\Resources\Settings\Institutions\InstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInstitutions extends ManageRecords
{
    protected static string $resource = InstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
