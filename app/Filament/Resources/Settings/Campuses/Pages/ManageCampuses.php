<?php

namespace App\Filament\Resources\Settings\Campuses\Pages;

use App\Filament\Resources\Settings\Campuses\CampusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCampuses extends ManageRecords
{
    protected static string $resource = CampusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
