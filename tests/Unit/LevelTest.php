<?php

namespace Tests\Unit;

use App\Models\Level;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_level(): void
    {
        $level = Level::factory()->create();

        $this->assertDatabaseHas('levels', ['id' => $level->id]);
    }
}
