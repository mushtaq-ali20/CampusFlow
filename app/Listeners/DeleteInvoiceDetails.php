<?php

namespace App\Listeners;

use App\Events\InvoiceDeleting;
use App\Models\Invoice;

class DeleteInvoiceDetails
{
    public function handle(InvoiceDeleting $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->invoice;

        $invoice->invoiceDetails()->delete();
    }
}
