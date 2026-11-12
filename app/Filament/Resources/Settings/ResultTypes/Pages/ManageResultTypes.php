<?php

namespace App\Filament\Resources\Settings\ResultTypes\Pages;

use App\Filament\Resources\Settings\ResultTypes\ResultTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResultTypes extends ManageRecords
{
    protected static string $resource = ResultTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
