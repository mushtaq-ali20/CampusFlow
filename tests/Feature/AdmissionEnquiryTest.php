<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\EnquiryStage;
use App\Enums\EnquiryStatus;
use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Filament\Resources\AdmissionEnquiries\Pages\CreateAdmissionEnquiry;
use App\Filament\Resources\AdmissionEnquiries\Pages\EditAdmissionEnquiry;
use App\Filament\Resources\AdmissionEnquiries\Pages\ListAdmissionEnquiries;
use App\Models\Admission;
use App\Models\AdmissionEnquiry;
use App\Models\Campus;
use App\Models\FeeVoucher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use App\Models\Year;
use App\Services\AdmissionEnquiryService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdmissionEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private Campus $branch;

    private SchoolClass $class;

    private Section $section;

    private AdmissionEnquiryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->travelTo('2026-11-11 10:00');

        $this->year = Year::factory()->create(['name' => '2026-2027', 'start' => '2026-04-01', 'end' => '2027-03-31']);
        $this->branch = Campus::factory()->create(['name' => 'Gulshan', 'code' => 'GUL']);
        $this->class = SchoolClass::factory()->create(['name' => 'Class 1']);
        $this->section = Section::factory()->create(['campus_id' => $this->branch->id, 'school_class_id' => $this->class->id, 'year_id' => $this->year->id, 'name' => 'A', 'capacity' => 2]);
        $this->service = app(AdmissionEnquiryService::class);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function enquiry(array $attributes = []): AdmissionEnquiry
    {
        return AdmissionEnquiry::factory()->create([
            'campus_id' => $this->branch->id,
            'year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'student_firstname' => 'Hamza',
            'student_lastname' => 'Tariq',
            'birthdate' => '2020-11-20',
            'guardian_name' => 'Tariq Mehmood',
            'guardian_phone' => '0300-1234567',
            ...$attributes,
        ]);
    }

    public function test_an_enquiry_is_numbered_by_branch_and_academic_year(): void
    {
        $this->assertSame('ENQ-GUL-2026-0001', $this->enquiry()->enquiry_number);
        $this->assertSame('ENQ-GUL-2026-0002', $this->enquiry()->enquiry_number);
    }

    public function test_a_registered_enquiry_goes_through_every_step_to_the_admission(): void
    {
        $admin = $this->actingAsRole('admin');
        $enquiry = $this->enquiry();

        $this->service->register($enquiry, $admin);
        $this->assertSame([EnquiryStatus::Registered, EnquiryStage::AgeApproval], [$enquiry->status, $enquiry->stage]);
        $this->assertSame('5 years 4 months', $this->service->ageLabel($enquiry));

        $this->service->completeStep($enquiry, $admin);
        $this->service->completeStep($enquiry, $admin);
        $this->assertSame(EnquiryStage::DocumentReview, $enquiry->stage);

        try {
            $this->service->completeStep($enquiry, $admin);
            $this->fail('The document review needs the required documents.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('B-Form or birth certificate', $exception->getMessage());
        }

        $enquiry->update(['documents_received' => ['birth_certificate', 'photographs', 'guardian_cnic']]);
        $this->service->completeStep($enquiry, $admin);
        $this->assertSame(EnquiryStage::EntryTest, $enquiry->stage);

        $this->service->scheduleTest($enquiry, now()->addDays(2), $admin);
        $this->assertNotNull($this->service->blockingReason($enquiry));
        $this->service->recordTestResult($enquiry, false, 'Weak in reading', $admin);
        $this->assertNotNull($this->service->blockingReason($enquiry));
        $this->service->recordTestResult($enquiry, true, null, $admin);
        $this->service->completeStep($enquiry, $admin);
        $this->assertSame(EnquiryStage::ReadyToAdmit, $enquiry->stage);
        $this->assertFalse($this->service->canCompleteStep($enquiry, $admin));

        $admission = $this->service->admit($enquiry, $this->section, $admin);

        $student = $admission->student;
        $this->assertSame('Hamza TARIQ', $student->name);
        $this->assertSame('2020-11-20', $student->birthdate);
        $this->assertSame($this->section->id, $admission->section_id);
        $this->assertSame(AdmissionStatus::Active, $admission->status);
        $guardian = $student->contacts()->sole();
        $this->assertSame('Tariq Mehmood', trim($guardian->name));
        $this->assertSame('0300-1234567', $guardian->phone->sole()->phone_number);
        $this->assertSame(EnquiryStatus::Admitted, $enquiry->fresh()->status);
        $this->assertNull($enquiry->fresh()->stage);
        $this->assertTrue($enquiry->fresh()->admission->is($admission));
        $this->assertCount(9, $enquiry->activities);
    }

    public function test_only_approvers_approve_the_age_and_the_fee_dues(): void
    {
        $secretary = $this->actingAsRole('secretary');
        $enquiry = $this->enquiry();
        $enquiry->update(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::AgeApproval]);

        $this->assertFalse($this->service->canCompleteStep($enquiry, $secretary));
        $this->expectException(DomainException::class);

        $this->service->completeStep($enquiry, $secretary);
    }

    public function test_the_front_desk_completes_the_document_review_and_the_entry_test(): void
    {
        $secretary = $this->actingAsRole('secretary');
        $enquiry = $this->enquiry(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::DocumentReview, 'documents_received' => ['birth_certificate', 'photographs', 'guardian_cnic']]);

        $this->service->completeStep($enquiry, $secretary);

        $this->assertSame(EnquiryStage::EntryTest, $enquiry->stage);
    }

    public function test_the_fee_check_shows_the_overdue_fees_of_the_brother_or_sister(): void
    {
        $sibling = Admission::factory()->create(['section_id' => $this->section->id]);
        FeeVoucher::factory()->create(['admission_id' => $sibling->id, 'voucher_number' => 'V1', 'month' => '2026-10-01', 'due_date' => '2026-10-10', 'net_amount' => 6500, 'paid_amount' => 1500]);
        FeeVoucher::factory()->create(['admission_id' => $sibling->id, 'voucher_number' => 'V2', 'due_date' => '2026-11-20', 'net_amount' => 6500, 'paid_amount' => 0]);

        $this->assertSame(5000, $this->service->siblingDues($this->enquiry(['sibling_admission_id' => $sibling->id])));
        $this->assertSame(0, $this->service->siblingDues($this->enquiry()));
    }

    public function test_a_child_is_not_admitted_to_a_full_section_or_another_class(): void
    {
        $admin = $this->actingAsRole('admin');
        Admission::factory()->count(2)->create(['section_id' => $this->section->id]);
        $otherClass = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id]);
        $enquiry = $this->enquiry(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::ReadyToAdmit]);

        $this->assertSame(0, $this->service->seatsLeft($this->section));

        foreach ([$this->section, $otherClass] as $section) {
            try {
                $this->service->admit($enquiry, $section, $admin);
                $this->fail('The child should not be admitted.');
            } catch (DomainException) {
                $this->assertNull($enquiry->fresh()->admission_id);
            }
        }
    }

    public function test_an_enquiry_not_ready_cannot_be_admitted(): void
    {
        $this->expectException(DomainException::class);

        $this->service->admit($this->enquiry(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::EntryTest]), $this->section, null);
    }

    public function test_the_seat_plan_and_the_summary_count_the_funnel_of_the_year(): void
    {
        Admission::factory()->create(['section_id' => $this->section->id]);
        Section::factory()->create(['campus_id' => $this->branch->id, 'school_class_id' => $this->class->id, 'year_id' => $this->year->id, 'name' => 'B', 'capacity' => 3]);
        $this->enquiry(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::EntryTest]);
        $this->enquiry(['status' => EnquiryStatus::Admitted]);
        $this->enquiry();
        $this->enquiry(['status' => EnquiryStatus::NotInterested]);

        $plan = $this->service->seatPlan(null, $this->year);
        $summary = $this->service->summary(null, $this->year);

        $this->assertSame([['campus' => 'Gulshan', 'class' => 'Class 1', 'capacity' => 5, 'admitted' => 1, 'inFunnel' => 1, 'seatsLeft' => 4]], $plan->all());
        $this->assertSame(['enquiries' => 4, 'registered' => 2, 'admitted' => 1, 'conversion' => 25.0], $summary);
    }

    public function test_the_front_desk_records_an_enquiry(): void
    {
        $user = $this->actingAsRole('secretary');

        Livewire::test(CreateAdmissionEnquiry::class)
            ->fillForm([
                'student_firstname' => 'Zara',
                'student_lastname' => 'Imran',
                'gender_id' => 1,
                'birthdate' => '2018-05-02',
                'campus_id' => $this->branch->id,
                'year_id' => $this->year->id,
                'school_class_id' => $this->class->id,
                'guardian_name' => 'Imran Khan',
                'guardian_phone' => '0321-7654321',
                'source' => 'sibling',
                'status' => 'interested',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $enquiry = AdmissionEnquiry::query()->sole();
        $this->assertSame('Zara Imran', $enquiry->student_name);
        $this->assertSame(EnquiryStatus::Interested, $enquiry->status);
        $this->assertSame($user->id, $enquiry->created_by);
        $this->assertSame('Enquiry received.', $enquiry->activities()->sole()->note);
    }

    public function test_staff_take_an_enquiry_through_the_funnel_from_its_page(): void
    {
        $this->actingAsRole('admin');
        $enquiry = $this->enquiry();

        Livewire::test(EditAdmissionEnquiry::class, ['record' => $enquiry->id])
            ->callAction('followUp', data: ['note' => 'Father will visit on Saturday', 'status' => 'interested'])
            ->callAction('register')
            ->assertActionVisible('completeStep')
            ->callAction('completeStep', data: ['note' => 'Age fits Class 1'])
            ->callAction('completeStep')
            ->assertActionHidden('admit');

        $enquiry->refresh();
        $this->assertSame(EnquiryStage::DocumentReview, $enquiry->stage);
        $enquiry->update(['stage' => EnquiryStage::ReadyToAdmit]);

        Livewire::test(EditAdmissionEnquiry::class, ['record' => $enquiry->id])
            ->assertActionVisible('admit')
            ->callAction('admit', data: ['section_id' => $this->section->id])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $this->assertSame(EnquiryStatus::Admitted, $enquiry->fresh()->status);
    }

    public function test_the_secretary_does_not_see_the_age_approval(): void
    {
        $this->actingAsRole('secretary');
        $enquiry = $this->enquiry(['status' => EnquiryStatus::Registered, 'stage' => EnquiryStage::AgeApproval]);

        Livewire::test(EditAdmissionEnquiry::class, ['record' => $enquiry->id])
            ->assertActionHidden('completeStep')
            ->assertActionHidden('register')
            ->assertActionVisible('followUp');
    }

    public function test_the_list_shows_the_funnel_and_only_the_branch_of_branch_staff(): void
    {
        $ours = $this->enquiry();
        $theirs = $this->enquiry(['campus_id' => Campus::factory()->create()->id]);
        $this->actingAsRole('secretary', $this->branch);

        Livewire::test(ListAdmissionEnquiries::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get(AdmissionEnquiryResource::getUrl())->assertSuccessful()->assertSee(['Seat plan', 'Enquiry to admission']);
    }

    public function test_viewers_cannot_open_the_enquiries(): void
    {
        $this->actingAsRole('viewer');

        $this->get(AdmissionEnquiryResource::getUrl())->assertForbidden();
    }
}
