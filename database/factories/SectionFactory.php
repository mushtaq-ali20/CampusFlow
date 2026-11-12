<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'school_class_id' => SchoolClass::factory(),
            'year_id' => Year::factory(),
            'name' => fake()->unique()->randomLetter(),
            'capacity' => fake()->numberBetween(20, 40),
        ];
    }
}
