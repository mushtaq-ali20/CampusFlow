<?php

namespace Tests\Unit;

use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_institution(): void
    {
        $institution = Institution::factory()->create();

        $this->assertDatabaseHas('institutions', ['id' => $institution->id]);
    }
}
