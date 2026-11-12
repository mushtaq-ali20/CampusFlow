<?php

namespace Tests\Unit;

use App\Models\Campus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_campus(): void
    {
        $campus = Campus::factory()->create();

        $this->assertDatabaseHas('campuses', ['id' => $campus->id]);
    }

    public function test_name_is_translatable(): void
    {
        $campus = Campus::factory()->create();

        $this->assertTrue(in_array('name', $campus->getTranslatableAttributes()));
    }
}
