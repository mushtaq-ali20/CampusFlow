<?php

namespace Database\Factories;

use App\Enums\FeeFrequency;
use App\Models\FeeHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeHead>
 */
class FeeHeadFactory extends Factory
{
    protected $model = FeeHead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'frequency' => FeeFrequency::Monthly,
            'is_active' => true,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(['frequency' => FeeFrequency::OneTime]);
    }

    public function annual(): static
    {
        return $this->state(['frequency' => FeeFrequency::Annual]);
    }
}
