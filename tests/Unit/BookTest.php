<?php

namespace Tests\Unit;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_book(): void
    {
        $book = Book::factory()->create();

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
}
