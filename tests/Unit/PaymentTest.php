<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_payment_belongs_to_invoice(): void
    {
        $payment = Payment::factory()->create();

        $this->assertInstanceOf(Invoice::class, $payment->invoice);
    }
}
