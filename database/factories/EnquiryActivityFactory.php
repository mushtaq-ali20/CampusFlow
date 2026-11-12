<?php

namespace Database\Factories;

use App\Models\AdmissionEnquiry;
use App\Models\EnquiryActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnquiryActivity>
 */
class EnquiryActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_enquiry_id' => AdmissionEnquiry::factory(),
            'note' => fake()->sentence(),
        ];
    }
}
