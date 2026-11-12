<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactRelationship;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'idnumber' => fake()->numerify('########'),
            'address' => fake()->streetAddress(),
            'relationship_id' => ContactRelationship::factory(),
        ];
    }
}
