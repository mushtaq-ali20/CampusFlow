<?php

namespace Tests\Unit;

use App\Models\ResultType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_result_type(): void
    {
        $type = ResultType::factory()->create();

        $this->assertDatabaseHas('result_types', ['id' => $type->id]);
    }

    public function test_name_is_translatable(): void
    {
        $type = ResultType::factory()->create();

        $this->assertTrue(in_array('name', $type->getTranslatableAttributes()));
    }
}
