<?php

namespace Tests\Unit;

use App\Models\Rhythm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhythmTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_rhythm(): void
    {
        $rhythm = Rhythm::factory()->create();

        $this->assertDatabaseHas('rhythms', ['id' => $rhythm->id]);
    }
}
