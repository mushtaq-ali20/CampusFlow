<?php

namespace Database\Factories;

use App\Enums\ComplainantType;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Models\Campus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * Define the model's default state: a new complaint due tomorrow.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'complaint_category_id' => ComplaintCategory::factory(),
            'complainant_type' => ComplainantType::Parent,
            'complainant_name' => fake()->name(),
            'complainant_phone' => '0300-'.fake()->numerify('#######'),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => ComplaintPriority::Medium,
            'status' => ComplaintStatus::New,
            'due_at' => now()->addDay(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => ['due_at' => now()->subHour()]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['status' => ComplaintStatus::Resolved, 'resolved_at' => now(), 'resolution' => 'Sorted out with the parents.']);
    }
}
