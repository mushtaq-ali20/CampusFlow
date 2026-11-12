<?php

namespace Tests\Unit;

use App\Models\Period;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_period(): void
    {
        $period = Period::factory()->create();

        $this->assertDatabaseHas('periods', ['id' => $period->id]);
    }

    public function test_period_belongs_to_year(): void
    {
        $period = Period::factory()->create();

        $this->assertInstanceOf(Year::class, $period->year);
    }
}
