<?php

namespace App\Filament\Resources\TimetablePeriods\Pages;

use App\Filament\Resources\TimetablePeriods\TimetablePeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTimetablePeriods extends ManageRecords
{
    protected static string $resource = TimetablePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
