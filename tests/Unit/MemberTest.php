<?php

namespace Tests\Unit;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_member(): void
    {
        $member = Member::factory()->create();

        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }
}
