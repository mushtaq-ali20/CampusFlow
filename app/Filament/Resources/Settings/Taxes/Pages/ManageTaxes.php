<?php

namespace App\Filament\Resources\Settings\Taxes\Pages;

use App\Filament\Resources\Settings\Taxes\TaxResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTaxes extends ManageRecords
{
    protected static string $resource = TaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
