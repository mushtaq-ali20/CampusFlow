<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_event(): void
    {
        $event = Event::factory()->create();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_event_belongs_to_teacher(): void
    {
        $event = Event::factory()->create();

        $this->assertInstanceOf(Teacher::class, $event->teacher);
    }

    public function test_length_attribute_returns_positive_hours(): void
    {
        $event = Event::factory()->create([
            'start' => '2025-04-01 09:00:00',
            'end' => '2025-04-01 11:00:00',
        ]);

        $this->assertGreaterThan(0, $event->length);
    }

    public function test_length_attribute_computes_correct_hours(): void
    {
        $event = Event::factory()->create([
            'start' => '2025-04-01 09:00:00',
            'end' => '2025-04-01 11:30:00',
        ]);

        $this->assertEquals(2.5, $event->length);
    }

    public function test_event_length_attribute_computes_correct_hours(): void
    {
        $event = Event::factory()->create([
            'start' => '2025-04-01 14:00:00',
            'end' => '2025-04-01 16:00:00',
        ]);

        $this->assertEquals(2.0, $event->event_length);
    }

    public function test_volume_attribute_computes_correct_hours(): void
    {
        $event = Event::factory()->create([
            'start' => '2025-04-01 10:00:00',
            'end' => '2025-04-01 11:30:00',
        ]);

        $this->assertEquals(1.5, $event->volume);
    }
}
