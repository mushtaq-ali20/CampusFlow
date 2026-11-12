<?php

namespace App\Filament\Resources\Settings\Coupons\Pages;

use App\Filament\Resources\Settings\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCoupons extends ManageRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
