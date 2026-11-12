<?php

namespace Tests\Unit;

use App\Models\Fee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_getter_divides_by_100(): void
    {
        $fee = Fee::create(['name' => 'Test Fee', 'price' => 50, 'product_code' => 'TF']);

        // The setter multiplies by 100 (50 * 100 = 5000 stored), getter divides by 100 (5000 / 100 = 50)
        $this->assertEquals(50, $fee->fresh()->price);
    }

    public function test_price_setter_multiplies_by_100(): void
    {
        $fee = Fee::create(['name' => 'Test Fee', 'price' => 25, 'product_code' => 'TF']);

        // Verify the raw DB value is multiplied
        $rawPrice = \DB::table('fees')->where('id', $fee->id)->value('price');
        $this->assertEquals(2500, $rawPrice);
    }

    public function test_price_with_currency_before(): void
    {
        config()->set('campusflow.currency_position', 'before');
        config()->set('campusflow.currency_symbol', '$');

        $fee = Fee::create(['name' => 'Test Fee', 'price' => 100, 'product_code' => 'TF']);

        $this->assertEquals('$ 100', $fee->price_with_currency);
    }

    public function test_price_with_currency_after(): void
    {
        config()->set('campusflow.currency_position', 'after');
        config()->set('campusflow.currency_symbol', 'EUR');

        $fee = Fee::create(['name' => 'Test Fee', 'price' => 100, 'product_code' => 'TF']);

        $this->assertEquals('100 EUR', $fee->price_with_currency);
    }
}
