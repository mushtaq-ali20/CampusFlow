<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\FeePayment;
use App\Models\FeeVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePayment>
 */
class FeePaymentFactory extends Factory
{
    protected $model = FeePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_voucher_id' => FeeVoucher::factory(),
            'campus_id' => fn (array $attributes): int => FeeVoucher::query()->findOrFail($attributes['fee_voucher_id'])->campus_id,
            'amount' => 1000,
            'paid_on' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
        ];
    }
}
