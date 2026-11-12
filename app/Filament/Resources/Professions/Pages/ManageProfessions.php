<?php

namespace App\Filament\Resources\Professions\Pages;

use App\Filament\Resources\Professions\ProfessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProfessions extends ManageRecords
{
    protected static string $resource = ProfessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
