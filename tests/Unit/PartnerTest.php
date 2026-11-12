<?php

namespace Tests\Unit;

use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_partner(): void
    {
        $partner = Partner::factory()->create();

        $this->assertDatabaseHas('partners', ['id' => $partner->id]);
    }
}
