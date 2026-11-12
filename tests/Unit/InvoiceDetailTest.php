<?php

namespace Tests\Unit;

use App\Models\InvoiceDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_invoice_detail(): void
    {
        $detail = InvoiceDetail::factory()->create();

        $this->assertDatabaseHas('invoice_details', ['id' => $detail->id]);
    }
}
