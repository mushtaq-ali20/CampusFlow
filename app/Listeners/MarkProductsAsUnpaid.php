<?php

namespace App\Listeners;

use App\Events\InvoiceDeleting;
use App\Models\Invoice;
use App\Traits\ReportsErrors;

class MarkProductsAsUnpaid
{
    use ReportsErrors;

    public function handle(InvoiceDeleting $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->invoice;

        try {
            foreach ($invoice->enrollments as $enrollment) {
                if ($enrollment->product) {
                    // $enrollment->product->markAsUnpaid();
                } else {
                    $this->reportError(
                        new \RuntimeException('Unable to delete invoice for enrollment #'.$enrollment->id),
                        'MarkProductsAsUnpaid::handle.enrollment',
                        ['invoice_id' => $invoice->id, 'enrollment_detail_id' => $enrollment->id]
                    );
                }
            }

            foreach ($invoice->scheduledPayments as $scheduledPayment) {
                if (! $scheduledPayment->product) {
                    continue;
                }

                $scheduledPayment->product->update(['status' => 1]);
                $scheduledPayment->product->enrollment?->markAsUnpaid();
            }
        } catch (\Throwable $e) {
            $this->reportError($e, 'MarkProductsAsUnpaid::handle', [
                'invoice_id' => $invoice->id,
            ]);
        }
    }
}
