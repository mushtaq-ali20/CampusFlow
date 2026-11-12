<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\ScheduledPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_scheduled_payment(): void
    {
        $payment = ScheduledPayment::factory()->create();

        $this->assertDatabaseHas('scheduled_payments', ['id' => $payment->id]);
    }

    public function test_scheduled_payment_belongs_to_enrollment(): void
    {
        $payment = ScheduledPayment::factory()->create();

        $this->assertInstanceOf(Enrollment::class, $payment->enrollment);
    }
}
