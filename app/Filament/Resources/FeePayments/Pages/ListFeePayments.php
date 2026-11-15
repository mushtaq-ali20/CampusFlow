<?php

namespace App\Filament\Resources\FeePayments\Pages;

use App\Filament\Resources\FeePayments\FeePaymentResource;
use App\Services\FeeService;
use Filament\Resources\Pages\ListRecords;

class ListFeePayments extends ListRecords
{
    protected static string $resource = FeePaymentResource::class;

    /**
     * The pills under the screen's name: the money received on the receipts the table shows, and this month's part.
     *
     * @return list<array{label: string, tone: string}>
     */
    public function getHeaderStats(): array
    {
        $query = $this->getFilteredTableQuery()->reorder();
        $amount = $query->getModel()->qualifyColumn('amount');
        $paidOn = $query->getModel()->qualifyColumn('paid_on');

        $thisMonth = (int) (clone $query)->whereBetween($paidOn, [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum($amount);

        return [
            ['label' => __(':amount received', ['amount' => FeeService::money((int) (clone $query)->sum($amount))]), 'tone' => 'success'],
            ['label' => __(':amount this month', ['amount' => FeeService::money($thisMonth)]), 'tone' => ''],
        ];
    }
}
