<?php

namespace App\Filament\Resources\Settings\Years\Pages;

use App\Filament\Resources\Settings\Years\YearResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageYears extends ManageRecords
{
    protected static string $resource = YearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
