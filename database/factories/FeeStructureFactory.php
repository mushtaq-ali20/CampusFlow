<?php

namespace Database\Factories;

use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    protected $model = FeeStructure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            'year_id' => Year::factory(),
            'campus_id' => null,
            'fee_head_id' => FeeHead::factory(),
            'amount' => fake()->numberBetween(5, 50) * 100,
        ];
    }
}
