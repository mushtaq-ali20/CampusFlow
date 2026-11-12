<?php

namespace Tests\Unit;

use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_comment(): void
    {
        $comment = Comment::factory()->create();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }
}
