<?php

namespace Tests\Unit;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }
}
