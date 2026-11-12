<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'campus_id' => Campus::factory(),
        ];
    }
}
