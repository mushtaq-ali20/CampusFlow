<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\FeeAdjustment;
use App\Models\FeeVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeAdjustment>
 */
class FeeAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state: a reduction waiting for approval.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_voucher_id' => FeeVoucher::factory(),
            'campus_id' => fn (array $attributes): int => FeeVoucher::query()->findOrFail($attributes['fee_voucher_id'])->campus_id,
            'amount' => -200,
            'reason' => 'Sibling concession',
            'status' => RequestStatus::Pending,
        ];
    }
}
