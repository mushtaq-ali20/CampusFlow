<?php

namespace Database\Factories;

use App\Models\Paymentmethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paymentmethod>
 */
class PaymentmethodFactory extends Factory
{
    protected $model = Paymentmethod::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'code' => fake()->unique()->lexify('???'),
        ];
    }
}
