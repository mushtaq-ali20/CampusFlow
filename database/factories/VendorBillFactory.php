<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Campus;
use App\Models\Vendor;
use App\Models\VendorBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorBill>
 */
class VendorBillFactory extends Factory
{
    /**
     * Define the model's default state: an unpaid bill of 10,000.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'vendor_id' => Vendor::factory(),
            'reference' => 'BILL-'.fake()->unique()->numerify('######'),
            'bill_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'amount' => 10000,
            'paid_amount' => 0,
            'status' => BillStatus::Unpaid,
        ];
    }
}
