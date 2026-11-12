<?php

namespace Tests\Unit;

use App\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_discount(): void
    {
        $discount = Discount::factory()->create();

        $this->assertDatabaseHas('discounts', ['id' => $discount->id]);
    }
}
