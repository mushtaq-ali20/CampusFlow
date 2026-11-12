<?php

namespace Database\Factories;

use App\Enums\VoucherStatus;
use App\Models\Admission;
use App\Models\FeeVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeVoucher>
 */
class FeeVoucherFactory extends Factory
{
    protected $model = FeeVoucher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $month = now()->startOfMonth();

        return [
            'voucher_number' => fn (array $attributes): string => 'V-'.$month->format('Ym').'-'.str_pad((string) $attributes['admission_id'], 5, '0', STR_PAD_LEFT),
            'admission_id' => Admission::factory(),
            'campus_id' => fn (array $attributes): int => Admission::query()->findOrFail($attributes['admission_id'])->campus_id,
            'school_class_id' => fn (array $attributes): int => Admission::query()->findOrFail($attributes['admission_id'])->school_class_id,
            'section_id' => fn (array $attributes): int => Admission::query()->findOrFail($attributes['admission_id'])->section_id,
            'year_id' => fn (array $attributes): int => Admission::query()->findOrFail($attributes['admission_id'])->year_id,
            'month' => $month->toDateString(),
            'due_date' => $month->copy()->addDays(9)->toDateString(),
            'gross_amount' => 1000,
            'discount_amount' => 0,
            'fine_amount' => 0,
            'net_amount' => 1000,
            'paid_amount' => 0,
            'status' => VoucherStatus::Unpaid,
        ];
    }
}
