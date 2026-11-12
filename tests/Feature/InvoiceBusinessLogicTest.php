<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceType;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_number_increments_for_type_and_year(): void
    {
        $invoiceType = InvoiceType::factory()->create();

        $invoice1 = Invoice::factory()->create([
            'invoice_type_id' => $invoiceType->id,
            'invoice_number' => 1,
        ]);

        $invoice2 = Invoice::factory()->create([
            'invoice_type_id' => $invoiceType->id,
            'invoice_number' => null,
        ]);

        $invoice2->setNumber();

        $this->assertEquals(2, $invoice2->fresh()->invoice_number);
    }

    public function test_paid_total_sums_payment_values(): void
    {
        $invoice = Invoice::factory()->create();

        Payment::factory()->create(['invoice_id' => $invoice->id, 'value' => 50]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'value' => 30]);

        // ValueTrait divides by 100, so we need to account for that
        $this->assertEquals(50 + 30, $invoice->fresh()->paidTotal());
    }

    public function test_balance_is_total_minus_paid(): void
    {
        $invoiceType = InvoiceType::factory()->create();
        $invoice = Invoice::factory()->create(['invoice_type_id' => $invoiceType->id]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Test',
            'product_code' => 'T',
            'product_id' => 1,
            'product_type' => 'App\\Models\\Fee',
            'price' => 100,
            'quantity' => 1,
        ]);

        Payment::factory()->create(['invoice_id' => $invoice->id, 'value' => 40]);

        $balance = $invoice->fresh()->balance;

        $this->assertEquals(100 - 40, $balance);
    }

    public function test_invoice_reference_auto_numbering(): void
    {
        $invoiceType = InvoiceType::factory()->create(['name' => 'FC']);
        $invoice = Invoice::factory()->create([
            'invoice_type_id' => $invoiceType->id,
            'invoice_number' => 42,
        ]);

        $reference = $invoice->invoice_reference;

        $this->assertStringContainsString('FC', $reference);
        $this->assertStringContainsString('42', $reference);
    }

    public function test_total_price_sums_invoice_details(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Item A',
            'product_code' => 'A',
            'product_id' => 1,
            'product_type' => 'App\\Models\\Fee',
            'price' => 50,
            'quantity' => 2,
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Item B',
            'product_code' => 'B',
            'product_id' => 2,
            'product_type' => 'App\\Models\\Fee',
            'price' => 30,
            'quantity' => 1,
        ]);

        // totalPrice = (50 * 2) + (30 * 1) = 130
        $this->assertEquals(130, $invoice->fresh()->totalPrice());
    }
}
