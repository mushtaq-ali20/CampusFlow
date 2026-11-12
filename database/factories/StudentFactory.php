<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'id' => User::factory(),
            'idnumber' => fake()->optional()->numerify('########'),
            'address' => fake()->optional()->address(),
            'birthdate' => fake()->optional()->date(),
            'gender_id' => fake()->optional()->numberBetween(1, 3),
        ];
    }
}
