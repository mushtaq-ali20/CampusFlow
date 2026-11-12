<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Campus;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state: two days of leave waiting for approval.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campus_id' => fn (array $attributes): int => User::query()->findOrFail($attributes['user_id'])->campus_id ?? Campus::factory()->create()->id,
            'leave_type_id' => LeaveType::factory(),
            'from_date' => '2026-03-10',
            'to_date' => '2026-03-11',
            'days' => 2,
            'reason' => 'Family event',
            'status' => RequestStatus::Pending,
        ];
    }
}
