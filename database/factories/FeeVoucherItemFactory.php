<?php

namespace Database\Factories;

use App\Enums\VoucherItemKind;
use App\Models\FeeVoucher;
use App\Models\FeeVoucherItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeVoucherItem>
 */
class FeeVoucherItemFactory extends Factory
{
    protected $model = FeeVoucherItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_voucher_id' => FeeVoucher::factory(),
            'fee_head_id' => null,
            'kind' => VoucherItemKind::Fee,
            'description' => 'Tuition Fee',
            'amount' => 1000,
        ];
    }
}
