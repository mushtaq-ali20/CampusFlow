<?php

namespace Tests\Unit;

use App\Models\Skills\SkillScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_skill_scale(): void
    {
        $scale = SkillScale::factory()->create();

        $this->assertDatabaseHas('skill_scales', ['id' => $scale->id]);
    }

    public function test_name_is_translatable(): void
    {
        $scale = SkillScale::factory()->create();

        $this->assertTrue(in_array('name', $scale->getTranslatableAttributes()));
    }
}
