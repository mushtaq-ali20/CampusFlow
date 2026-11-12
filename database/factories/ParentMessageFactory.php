<?php

namespace Database\Factories;

use App\Models\ParentMessage;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentMessage>
 */
class ParentMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'channel' => 'whatsapp',
            'recipient_name' => fake()->name(),
            'phone' => '92300'.fake()->numerify('#######'),
            'body' => fake()->sentence(),
        ];
    }
}
