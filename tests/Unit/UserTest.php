<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_has_required_attributes(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->firstname);
        $this->assertNotNull($user->lastname);
        $this->assertNotNull($user->email);
    }

    public function test_user_uses_soft_deletes(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
