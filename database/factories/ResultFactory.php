<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Result;
use App\Models\ResultType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Result>
 */
class ResultFactory extends Factory
{
    protected $model = Result::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'result_type_id' => ResultType::factory(),
        ];
    }
}
