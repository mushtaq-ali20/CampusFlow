<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\DisciplineCategory;
use App\Models\DisciplineRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The branch, section, points and consequence are copied from the admission and the category when it is saved.
 *
 * @extends Factory<DisciplineRecord>
 */
class DisciplineRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'discipline_category_id' => DisciplineCategory::factory(),
            'date' => now()->toDateString(),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
