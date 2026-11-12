<?php

namespace Database\Factories;

use App\Models\FeeInstallment;
use App\Models\FeeVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeInstallment>
 */
class FeeInstallmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_voucher_id' => FeeVoucher::factory(),
            'sequence' => 1,
            'amount' => 500,
            'due_date' => now()->addMonth()->toDateString(),
        ];
    }
}
