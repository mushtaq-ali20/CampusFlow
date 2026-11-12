<?php

namespace Database\Factories;

use App\Models\ContactRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRelationship>
 */
class ContactRelationshipFactory extends Factory
{
    protected $model = ContactRelationship::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}
