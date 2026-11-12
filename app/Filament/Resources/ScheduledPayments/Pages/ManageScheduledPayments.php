<?php

namespace App\Filament\Resources\ScheduledPayments\Pages;

use App\Filament\Resources\ScheduledPayments\ScheduledPaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageScheduledPayments extends ManageRecords
{
    protected static string $resource = ScheduledPaymentResource::class;
}
