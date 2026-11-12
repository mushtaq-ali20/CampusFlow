<?php

namespace Tests\Unit;

use App\Models\Fee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_fee(): void
    {
        $fee = Fee::factory()->create();

        $this->assertDatabaseHas('fees', ['id' => $fee->id]);
    }
}
