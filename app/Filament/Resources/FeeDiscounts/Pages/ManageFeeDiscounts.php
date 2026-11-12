<?php

namespace App\Filament\Resources\FeeDiscounts\Pages;

use App\Filament\Resources\FeeDiscounts\FeeDiscountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFeeDiscounts extends ManageRecords
{
    protected static string $resource = FeeDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
