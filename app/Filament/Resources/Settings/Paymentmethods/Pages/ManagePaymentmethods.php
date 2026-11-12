<?php

namespace App\Filament\Resources\Settings\Paymentmethods\Pages;

use App\Filament\Resources\Settings\Paymentmethods\PaymentmethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentmethods extends ManageRecords
{
    protected static string $resource = PaymentmethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
