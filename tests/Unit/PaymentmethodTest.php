<?php

namespace Tests\Unit;

use App\Models\Paymentmethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentmethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_paymentmethod(): void
    {
        $method = Paymentmethod::factory()->create();

        $this->assertDatabaseHas('paymentmethods', ['id' => $method->id]);
    }
}
