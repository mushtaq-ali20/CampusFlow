<?php

namespace App\Filament\Resources\VendorBills\Pages;

use App\Enums\BillStatus;
use App\Filament\Resources\VendorBills\VendorBillResource;
use App\Services\FeeService;
use App\Services\PayableService;
use DomainException;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ManageVendorBills extends ManageRecords
{
    protected static string $resource = VendorBillResource::class;

    /**
     * The pill under the screen's name: how many of the bills the table shows are still owed, and how much.
     *
     * @return list<array{label: string, tone: string}>
     */
    public function getHeaderStats(): array
    {
        $model = $this->getFilteredTableQuery()->getModel();
        $open = $this->getFilteredTableQuery()->reorder()->whereIn($model->qualifyColumn('status'), [BillStatus::Unpaid, BillStatus::Partial]);
        $owed = (int) (clone $open)->sum(DB::raw($model->qualifyColumn('amount').' - '.$model->qualifyColumn('paid_amount')));

        return [
            ['label' => __(':count unpaid · :amount', ['count' => number_format((clone $open)->count()), 'amount' => FeeService::money($owed)]), 'tone' => 'danger'],
        ];
    }

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
