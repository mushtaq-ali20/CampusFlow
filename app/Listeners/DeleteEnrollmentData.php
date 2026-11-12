<?php

namespace App\Listeners;

use App\Events\EnrollmentDeleting;
use App\Models\InvoiceDetail;
use App\Traits\ReportsErrors;

class DeleteEnrollmentData
{
    use ReportsErrors;

    public function handle(EnrollmentDeleting $event): void
    {
        try {
            /** @var InvoiceDetail $invoiceDetail */
            foreach ($event->enrollment->invoiceDetails as $invoiceDetail) {
                $invoiceDetail->delete();
                if ($invoiceDetail->invoice->invoiceDetails->count() === 0) {
                    $invoiceDetail->invoice->delete();
                }
            }
        } catch (\Throwable $e) {
            $this->reportError($e, 'DeleteEnrollmentData::handle', [
                'enrollment_id' => $event->enrollment->id,
            ]);
        }
    }
}
