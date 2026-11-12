<?php

namespace Tests\Unit;

use App\Models\Profession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_profession(): void
    {
        $profession = Profession::factory()->create();

        $this->assertDatabaseHas('professions', ['id' => $profession->id]);
    }
}
