<?php

namespace Tests\Unit;

use App\Models\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_phone_number(): void
    {
        $phone = PhoneNumber::factory()->create();

        $this->assertDatabaseHas('phone_numbers', ['id' => $phone->id]);
    }
}
