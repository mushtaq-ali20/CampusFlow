<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'responsable_id' => User::factory(),
            'invoice_id' => Invoice::factory(),
            'payment_method' => null,
            'value' => fake()->numberBetween(100, 10000),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
