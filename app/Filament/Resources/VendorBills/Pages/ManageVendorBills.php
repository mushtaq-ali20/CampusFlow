<?php

namespace App\Filament\Resources\VendorBills\Pages;

use App\Filament\Resources\VendorBills\VendorBillResource;
use App\Services\PayableService;
use DomainException;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageVendorBills extends ManageRecords
{
    protected static string $resource = VendorBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Record a bill'))
                ->using(function (array $data, CreateAction $action): Model {
                    try {
                        return app(PayableService::class)->recordBill($data, auth()->user());
                    } catch (DomainException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        $action->halt();
                    }
                })
                ->successNotificationTitle(__('Bill recorded')),
        ];
    }
}
