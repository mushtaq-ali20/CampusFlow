<?php

namespace Database\Factories;

use App\Models\Profession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profession>
 */
class ProfessionFactory extends Factory
{
    protected $model = Profession::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
        ];
    }
}
