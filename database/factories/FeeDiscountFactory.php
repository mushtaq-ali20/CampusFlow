<?php

namespace Database\Factories;

use App\Enums\DiscountMode;
use App\Enums\DiscountType;
use App\Models\FeeDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeDiscount>
 */
class FeeDiscountFactory extends Factory
{
    protected $model = FeeDiscount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => DiscountType::Other,
            'mode' => DiscountMode::Percent,
            'value' => 10,
            'fee_head_id' => null,
        ];
    }

    public function fixed(int $amount): static
    {
        return $this->state(['mode' => DiscountMode::Fixed, 'value' => $amount]);
    }

    public function percent(int $percent): static
    {
        return $this->state(['mode' => DiscountMode::Percent, 'value' => $percent]);
    }
}
