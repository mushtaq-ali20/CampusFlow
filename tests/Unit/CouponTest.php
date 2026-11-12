<?php

namespace Tests\Unit;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }
}
