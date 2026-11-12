<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDetail>
 */
class InvoiceDetailFactory extends Factory
{
    protected $model = InvoiceDetail::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'product_name' => fake()->word(),
            'product_code' => fake()->word(),
            'product_id' => fake()->randomNumber(),
            'product_type' => fake()->word(),
            'price' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
