<?php

namespace App\Filament\Resources\Settings\AttendanceTypes\Pages;

use App\Filament\Resources\Settings\AttendanceTypes\AttendanceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceTypes extends ManageRecords
{
    protected static string $resource = AttendanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
