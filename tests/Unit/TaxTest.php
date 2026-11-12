<?php

namespace Tests\Unit;

use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tax(): void
    {
        $tax = Tax::factory()->create();

        $this->assertDatabaseHas('taxes', ['id' => $tax->id]);
    }
}
