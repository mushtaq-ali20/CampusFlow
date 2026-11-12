<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Result;
use App\Models\ResultType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_result(): void
    {
        $result = Result::factory()->create();

        $this->assertDatabaseHas('results', ['id' => $result->id]);
    }

    public function test_result_belongs_to_enrollment(): void
    {
        $result = Result::factory()->create();

        $this->assertInstanceOf(Enrollment::class, $result->enrollment);
    }

    public function test_result_belongs_to_result_type(): void
    {
        $result = Result::factory()->create();

        $this->assertInstanceOf(ResultType::class, $result->result_name);
    }
}
