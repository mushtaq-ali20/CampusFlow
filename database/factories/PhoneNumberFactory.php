<?php

namespace Database\Factories;

use App\Models\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhoneNumber>
 */
class PhoneNumberFactory extends Factory
{
    protected $model = PhoneNumber::class;

    public function definition(): array
    {
        return [
            'phoneable_id' => fake()->randomNumber(),
            'phoneable_type' => fake()->word(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }
}
