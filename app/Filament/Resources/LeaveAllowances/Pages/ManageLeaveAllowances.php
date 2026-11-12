<?php

namespace App\Filament\Resources\LeaveAllowances\Pages;

use App\Filament\Resources\LeaveAllowances\LeaveAllowanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveAllowances extends ManageRecords
{
    protected static string $resource = LeaveAllowanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
