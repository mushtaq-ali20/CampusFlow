<?php

namespace Tests\Feature;

use App\Enums\PtmMeetingStatus;
use App\Filament\Resources\PtmEvents\Pages\CreatePtmEvent;
use App\Filament\Resources\PtmEvents\Pages\ViewPtmEvent;
use App\Filament\Resources\PtmEvents\PtmEventResource;
use App\Filament\Resources\PtmEvents\RelationManagers\MeetingsRelationManager;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\Contact;
use App\Models\ParentMessage;
use App\Models\PhoneNumber;
use App\Models\PtmEvent;
use App\Models\PtmMeeting;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\PtmService;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PtmTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private Campus $branch;

    private Teacher $classTeacher;

    private Section $sectionA;

    private Section $sectionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->year = Year::factory()->create(['name' => '2026-2027']);
        $this->branch = Campus::factory()->create();
        $this->classTeacher = Teacher::factory()->create();
        $this->sectionA = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id, 'name' => 'A', 'class_teacher_id' => $this->classTeacher->id]);
        $this->sectionB = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id, 'name' => 'B']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function student(Section $section, string $firstname): Admission
    {
        $student = Student::factory()->create(['id' => User::factory()->create(['firstname' => $firstname, 'lastname' => 'Khan'])->id]);

        return Admission::factory()->create(['student_id' => $student->id, 'section_id' => $section->id]);
    }

    private function event(array $sections): PtmEvent
    {
        $event = PtmEvent::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id, 'date' => '2026-11-21', 'starts_at' => '09:00:00', 'slot_minutes' => 10]);
        $event->sections()->attach(collect($sections)->pluck('id'));

        return $event;
    }

    public function test_each_section_gets_its_own_row_of_slots_in_alphabetical_order(): void
    {
        $zara = $this->student($this->sectionA, 'Zara');
        $ali = $this->student($this->sectionA, 'Ali');
        $bilal = $this->student($this->sectionB, 'Bilal');
        $event = $this->event([$this->sectionA, $this->sectionB]);

        $this->assertSame(3, app(PtmService::class)->generateMeetings($event));

        $slots = $event->meetings()->get()->keyBy('admission_id');
        $this->assertSame('09:00:00', $slots[$ali->id]->slot_time);
        $this->assertSame('09:10:00', $slots[$zara->id]->slot_time);
        $this->assertSame('09:00:00', $slots[$bilal->id]->slot_time);
        $this->assertSame($this->classTeacher->id, $slots[$ali->id]->teacher_id);
        $this->assertSame(PtmMeetingStatus::Scheduled, $slots[$ali->id]->status);
    }

    public function test_generating_again_adds_new_students_after_the_others_and_drops_sections_removed(): void
    {
        $this->student($this->sectionA, 'Ali');
        $this->student($this->sectionB, 'Bilal');
        $met = $this->student($this->sectionB, 'Danish');
        $event = $this->event([$this->sectionA, $this->sectionB]);
        $service = app(PtmService::class);
        $service->generateMeetings($event);
        $event->meetings()->where('admission_id', $met->id)->update(['status' => PtmMeetingStatus::Met]);

        $newcomer = $this->student($this->sectionA, 'Aaron');
        $event->sections()->detach($this->sectionB->id);

        $this->assertSame(1, $service->generateMeetings($event));
        $this->assertSame('09:10:00', $event->meetings()->where('admission_id', $newcomer->id)->sole()->slot_time);
        $this->assertSame(['Ali', 'Danish', 'Aaron'], $event->meetings()->orderBy('id')->get()->map(fn (PtmMeeting $meeting): string => $meeting->admission->student->firstname)->all());
    }

    public function test_staff_plan_a_meeting_day_and_the_slots_are_created(): void
    {
        $this->actingAsRole('secretary');
        $this->student($this->sectionA, 'Ali');
        $this->student($this->sectionA, 'Zara');

        Livewire::test(CreatePtmEvent::class)
            ->fillForm([
                'title' => 'Mid-term PTM',
                'campus_id' => $this->branch->id,
                'year_id' => $this->year->id,
                'date' => '2026-11-21',
                'starts_at' => '09:00',
                'slot_minutes' => 15,
                'sections' => [$this->sectionA->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('2 slots created');

        $this->assertSame(['09:00:00', '09:15:00'], PtmMeeting::query()->orderBy('slot_time')->pluck('slot_time')->all());
    }

    public function test_class_teachers_see_and_record_the_meetings_of_their_sections_only(): void
    {
        $ali = $this->student($this->sectionA, 'Ali');
        $bilal = $this->student($this->sectionB, 'Bilal');
        $event = $this->event([$this->sectionA, $this->sectionB]);
        app(PtmService::class)->generateMeetings($event);
        $own = $event->meetings()->where('admission_id', $ali->id)->sole();
        $other = $event->meetings()->where('admission_id', $bilal->id)->sole();
        $otherDay = $this->event([$this->sectionB]);
        $this->actingAs($this->classTeacher->user);

        $this->assertTrue(PtmEventResource::canAccess());
        $this->assertSame([$event->id], PtmEventResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($otherDay->id, PtmEventResource::getEloquentQuery()->pluck('id'));
        $this->get(ViewPtmEvent::getUrl(['record' => $event]))->assertSuccessful();

        Livewire::test(MeetingsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => ViewPtmEvent::class])
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->callAction(TestAction::make('record')->table($own), data: [
                'notes' => 'Reading has improved',
                'action_items' => 'Read 20 minutes at home every day',
                'action_due_on' => '2026-12-15',
            ])
            ->assertHasNoActionErrors();

        $own->refresh();
        $this->assertSame(PtmMeetingStatus::Met, $own->status);
        $this->assertSame('Read 20 minutes at home every day', $own->action_items);
        $this->assertTrue($own->hasOpenAction());
    }

    public function test_teachers_do_not_plan_meeting_days(): void
    {
        $this->actingAs($this->classTeacher->user);

        $this->assertFalse(PtmEventResource::canCreate());
    }

    public function test_the_parents_are_invited_on_whatsapp(): void
    {
        $this->actingAsRole('secretary');
        $ali = $this->student($this->sectionA, 'Ali');
        $contact = Contact::factory()->create(['student_id' => $ali->student_id]);
        PhoneNumber::query()->create(['phoneable_id' => $contact->id, 'phoneable_type' => Contact::class, 'phone_number' => '0300-1234567']);
        $event = $this->event([$this->sectionA]);
        app(PtmService::class)->generateMeetings($event);
        $meeting = $event->meetings()->sole();

        $invitation = app(PtmService::class)->invitation($meeting);
        $this->assertStringContainsString('Saturday 21 November 2026 at 09:00 AM', $invitation);
        $this->assertStringContainsString('Ali KHAN', $invitation);

        Livewire::test(MeetingsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => ViewPtmEvent::class])
            ->callAction(TestAction::make('whatsappParent')->table($meeting), data: ['phone' => '923001234567', 'body' => $invitation])
            ->assertHasNoActionErrors();

        $this->assertSame(PtmMeetingStatus::Invited, $meeting->fresh()->status);
        $this->assertTrue(ParentMessage::query()->sole()->about->is($meeting));
    }

    public function test_viewers_cannot_open_the_meetings(): void
    {
        $this->actingAsRole('viewer');

        $this->get(PtmEventResource::getUrl())->assertForbidden();
    }
}
