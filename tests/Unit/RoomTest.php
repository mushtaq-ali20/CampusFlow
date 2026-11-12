<?php

namespace Tests\Unit;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_room(): void
    {
        $room = Room::factory()->create();

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }
}
