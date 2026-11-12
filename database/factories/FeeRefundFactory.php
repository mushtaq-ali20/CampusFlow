<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeRefund>
 */
class FeeRefundFactory extends Factory
{
    /**
     * Define the model's default state: a refund waiting for approval.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_payment_id' => FeePayment::factory(),
            'campus_id' => fn (array $attributes): int => FeePayment::query()->findOrFail($attributes['fee_payment_id'])->campus_id,
            'amount' => 100,
            'reason' => 'Paid twice',
            'method' => PaymentMethod::Cash,
            'status' => RequestStatus::Pending,
        ];
    }
}
