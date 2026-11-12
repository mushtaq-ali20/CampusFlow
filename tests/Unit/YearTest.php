<?php

namespace Tests\Unit;

use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_year(): void
    {
        $year = Year::factory()->create();

        $this->assertDatabaseHas('years', ['id' => $year->id]);
    }
}
