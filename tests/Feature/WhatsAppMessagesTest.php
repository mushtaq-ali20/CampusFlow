<?php

namespace Tests\Feature;

use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\Contact;
use App\Models\ContactRelationship;
use App\Models\ParentMessage;
use App\Models\Period;
use App\Models\PhoneNumber;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppMessagesTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        Period::factory()->create();
        $this->student = Student::factory()->create();
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function addContact(string $firstname, string $relationship, string ...$phones): Contact
    {
        $contact = Contact::factory()->create([
            'student_id' => $this->student->id,
            'firstname' => $firstname,
            'lastname' => 'Khan',
            'relationship_id' => ContactRelationship::factory()->create(['name' => $relationship])->id,
        ]);

        foreach ($phones as $phone) {
            PhoneNumber::query()->create(['phoneable_id' => $contact->id, 'phoneable_type' => Contact::class, 'phone_number' => $phone]);
        }

        return $contact;
    }

    public function test_local_and_international_numbers_are_normalized_for_whatsapp(): void
    {
        $service = app(WhatsAppService::class);

        $this->assertSame('923001234567', $service->normalize('0300-1234567'));
        $this->assertSame('923001234567', $service->normalize('+92 300 1234567'));
        $this->assertSame('923001234567', $service->normalize('0092 300 1234567'));
        $this->assertSame('923001234567', $service->normalize('300 1234567'));
        $this->assertSame('971501234567', $service->normalize('+971 50 123 4567'));
        $this->assertNull($service->normalize('12345'));
        $this->assertNull($service->normalize(null));
    }

    public function test_the_country_code_of_local_numbers_is_configurable(): void
    {
        config(['campusflow.whatsapp_country_code' => '44']);

        $this->assertSame('447911123456', app(WhatsAppService::class)->normalize('07911 123456'));
    }

    public function test_the_link_opens_the_chat_with_the_message_written(): void
    {
        $link = app(WhatsAppService::class)->link('0300-1234567', "Dear parent,\nFee due: Rs 6,500 & fine");

        $this->assertSame('https://wa.me/923001234567?text=Dear%20parent%2C%0AFee%20due%3A%20Rs%206%2C500%20%26%20fine', $link);
    }

    public function test_recipients_are_the_contacts_then_the_student_without_duplicates_or_unusable_numbers(): void
    {
        $this->addContact('Imran', 'Father', '0300-1234567', '123');
        $this->addContact('Sadia', 'Mother', '0321-7654321', '+92 300 1234567');
        PhoneNumber::query()->create(['phoneable_id' => $this->student->id, 'phoneable_type' => Student::class, 'phone_number' => '0333-1112223']);

        $recipients = app(WhatsAppService::class)->recipientsFor($this->student->fresh());

        $this->assertSame(['923001234567', '923217654321', '923331112223'], array_map('strval', array_keys($recipients)));
        $this->assertSame('Imran Khan (Father) · +923001234567', $recipients['923001234567']);
        $this->assertStringContainsString('(Student)', $recipients['923331112223']);
    }

    public function test_staff_prepare_a_message_to_the_parents_from_the_student_profile(): void
    {
        $user = $this->actingAsRole('secretary');
        $this->addContact('Imran', 'Father', '0300-1234567');

        Livewire::test(ViewStudent::class, ['record' => $this->student->id])
            ->assertActionVisible('whatsappParent')
            ->callAction('whatsappParent', data: ['phone' => '923001234567', 'body' => 'Please collect the report card.'])
            ->assertHasNoActionErrors()
            ->assertNotified('Message ready');

        $message = ParentMessage::query()->sole();
        $this->assertSame($this->student->id, $message->student_id);
        $this->assertSame('923001234567', $message->phone);
        $this->assertSame('Please collect the report card.', $message->body);
        $this->assertSame('Imran Khan (Father) · +923001234567', $message->recipient_name);
        $this->assertSame($user->id, $message->sent_by);
        $this->assertNull($message->about_type);
    }

    public function test_a_message_cannot_go_to_a_number_that_is_not_a_contact_of_the_student(): void
    {
        $this->actingAsRole('secretary');
        $this->addContact('Imran', 'Father', '0300-1234567');

        Livewire::test(ViewStudent::class, ['record' => $this->student->id])
            ->callAction('whatsappParent', data: ['phone' => '923009999999', 'body' => 'Hello']);

        $this->assertDatabaseCount('parent_messages', 0);
    }

    public function test_staff_without_the_permission_cannot_message_parents(): void
    {
        $role = Role::create(['name' => 'registrar']);
        $role->givePermissionTo('enrollments.view');
        $this->actingAsRole('registrar');

        Livewire::test(ViewStudent::class, ['record' => $this->student->id])
            ->assertActionHidden('whatsappParent');
    }
}
