<?php

namespace Database\Factories;

use App\Models\InvoiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceType>
 */
class InvoiceTypeFactory extends Factory
{
    protected $model = InvoiceType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'description' => fake()->sentence(),
        ];
    }
}
