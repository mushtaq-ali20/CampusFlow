<?php

namespace Database\Factories;

use App\Models\Rhythm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rhythm>
 */
class RhythmFactory extends Factory
{
    protected $model = Rhythm::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
