<?php

namespace Tests\Unit;

use App\Models\EvaluationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_evaluation_type(): void
    {
        $type = EvaluationType::factory()->create();

        $this->assertDatabaseHas('evaluation_types', ['id' => $type->id]);
    }
}
