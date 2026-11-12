<?php

namespace Tests\Unit;

use App\Models\ContactRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_contact_relationship(): void
    {
        $relationship = ContactRelationship::factory()->create();

        $this->assertDatabaseHas('contact_relationships', ['id' => $relationship->id]);
    }

    public function test_name_is_translatable(): void
    {
        $relationship = ContactRelationship::factory()->create();

        $this->assertTrue(in_array('name', $relationship->getTranslatableAttributes()));
    }
}
