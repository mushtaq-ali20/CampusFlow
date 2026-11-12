<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\ContactRelationship;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_contact(): void
    {
        $contact = Contact::factory()->create();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_contact_belongs_to_student(): void
    {
        $contact = Contact::factory()->create();

        $this->assertInstanceOf(Student::class, $contact->student);
    }

    public function test_contact_belongs_to_relationship(): void
    {
        $contact = Contact::factory()->create();

        $this->assertInstanceOf(ContactRelationship::class, $contact->relationship);
    }
}
