<?php

namespace App\Filament\Resources\PtmEvents\Pages;

use App\Filament\Resources\PtmEvents\PtmEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPtmEvents extends ListRecords
{
    protected static string $resource = PtmEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New meeting day')),
        ];
    }
}
