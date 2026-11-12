<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Event;
use App\Models\Room;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'course_id' => Course::factory(),
            'room_id' => Room::factory(),
            'start' => fake()->dateTime(),
            'end' => fake()->dateTime(),
            'name' => fake()->word(),
        ];
    }
}
