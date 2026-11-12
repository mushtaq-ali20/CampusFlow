<?php

namespace App\Filament\Resources\Settings\Discounts\Pages;

use App\Filament\Resources\Settings\Discounts\DiscountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends ManageRecords
{
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
