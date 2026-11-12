<?php

namespace Database\Factories;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStage;
use App\Enums\EnquiryStatus;
use App\Models\AdmissionEnquiry;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionEnquiry>
 */
class AdmissionEnquiryFactory extends Factory
{
    /**
     * Define the model's default state: an enquiry the front desk follows up.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'year_id' => Year::factory(),
            'school_class_id' => SchoolClass::factory(),
            'student_firstname' => fake()->firstName(),
            'student_lastname' => fake()->lastName(),
            'gender_id' => fake()->numberBetween(0, 2),
            'birthdate' => fake()->dateTimeBetween('-12 years', '-4 years')->format('Y-m-d'),
            'guardian_name' => fake()->name(),
            'guardian_relation' => 'Father',
            'guardian_phone' => '0300-'.fake()->numerify('#######'),
            'source' => EnquirySource::WalkIn,
            'status' => EnquiryStatus::InProcess,
        ];
    }

    public function registered(EnquiryStage $stage = EnquiryStage::AgeApproval): static
    {
        return $this->state(fn (): array => ['status' => EnquiryStatus::Registered, 'stage' => $stage]);
    }
}
