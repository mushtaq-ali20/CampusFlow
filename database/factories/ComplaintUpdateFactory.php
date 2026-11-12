<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\ComplaintUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintUpdate>
 */
class ComplaintUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'note' => fake()->sentence(),
        ];
    }
}
