<?php

namespace App\Filament\Resources\Settings\Rooms\Pages;

use App\Filament\Resources\Settings\Rooms\RoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRooms extends ManageRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
