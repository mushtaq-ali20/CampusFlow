<?php

namespace App\Filament\Resources\Settings\Fees\Pages;

use App\Filament\Resources\Settings\Fees\FeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFees extends ManageRecords
{
    protected static string $resource = FeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
