<?php

namespace Tests\Feature;

use App\Enums\ApplicantStage;
use App\Enums\EmployeeStatus;
use App\Enums\OpeningStatus;
use App\Filament\Resources\JobApplicants\JobApplicantResource;
use App\Filament\Resources\JobApplicants\Pages\ManageJobApplicants;
use App\Filament\Resources\JobOpenings\JobOpeningResource;
use App\Filament\Resources\JobOpenings\Pages\ManageJobOpenings;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\JobApplicant;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\RecruitmentService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private JobOpening $opening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branch = Campus::factory()->create();
        $this->opening = JobOpening::factory()->create(['campus_id' => $this->branch->id, 'title' => 'Maths Teacher', 'department' => 'Teaching', 'positions' => 1]);
    }

    private function actingAsRole(string $role, ?int $campusId = null): User
    {
        $user = User::factory()->create(['campus_id' => $campusId]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function applicant(array $attributes = []): JobApplicant
    {
        return JobApplicant::factory()->create(['job_opening_id' => $this->opening->id, 'firstname' => 'bilal', 'lastname' => 'ahmed', 'email' => 'bilal@example.com', ...$attributes]);
    }

    private function service(): RecruitmentService
    {
        return app(RecruitmentService::class);
    }

    private function offered(array $attributes = []): JobApplicant
    {
        $applicant = $this->applicant($attributes);
        $this->service()->shortlist($applicant);
        $this->service()->scheduleInterview($applicant, Carbon::parse('2026-03-10 10:00'));
        $this->service()->recordInterview($applicant, 4, 'Good with children');

        return $this->service()->offer($applicant, 45000);
    }

    public function test_an_applicant_goes_through_the_steps_to_an_offer(): void
    {
        $interviewer = User::factory()->create();
        $applicant = $this->applicant();

        $this->assertSame(ApplicantStage::Shortlisted, $this->service()->shortlist($applicant)->stage);

        $this->service()->scheduleInterview($applicant, Carbon::parse('2026-03-10 10:00'), $interviewer);
        $this->assertSame([ApplicantStage::Interview, '2026-03-10 10:00', $interviewer->id], [$applicant->stage, $applicant->interview_at->format('Y-m-d H:i'), $applicant->interviewer_id]);

        $this->service()->recordInterview($applicant, 4, 'Good with children');
        $this->service()->offer($applicant, 45000);

        $this->assertSame([ApplicantStage::Offered, 4, 45000], [$applicant->stage, $applicant->rating, $applicant->offered_salary]);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $applicant = $this->applicant();

        foreach ([
            fn () => $this->service()->scheduleInterview($applicant, now()),
            fn () => $this->service()->recordInterview($applicant, 4),
            fn () => $this->service()->offer($applicant, 45000),
            fn () => $this->service()->hire($applicant, now()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The step should have been refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(ApplicantStage::Applied, $applicant->refresh()->stage);
    }

    public function test_an_offer_needs_a_recorded_interview_and_a_rating_from_1_to_5(): void
    {
        $applicant = $this->applicant();
        $this->service()->shortlist($applicant);
        $this->service()->scheduleInterview($applicant, now());

        foreach ([
            fn () => $this->service()->offer($applicant, 45000),
            fn () => $this->service()->recordInterview($applicant, 6),
            fn () => $this->service()->recordInterview($applicant, 0),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The step should have been refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_hiring_makes_the_employee_file_and_closes_a_filled_opening(): void
    {
        $applicant = $this->offered(['cnic' => '35202-1234567-1', 'phone' => '0300-1112223']);

        $profile = $this->service()->hire($applicant, Carbon::parse('2026-04-01'));

        $this->assertSame(
            ['Bilal AHMED', 'bilal@example.com', $this->branch->id, 'Maths Teacher', 'Teaching', 45000, '2026-04-01', EmployeeStatus::Active, '35202-1234567-1', '0300-1112223'],
            [$profile->user->name, $profile->user->email, $profile->campus_id, $profile->designation, $profile->department, $profile->basic_salary, $profile->joined_on->format('Y-m-d'), $profile->status, $profile->cnic, $profile->phone],
        );
        $this->assertFalse($profile->user->hasAnyRole(['admin', 'secretary', 'viewer']));
        $this->assertSame([ApplicantStage::Hired, $profile->id], [$applicant->refresh()->stage, $applicant->employee_profile_id]);
        $this->assertSame(OpeningStatus::Closed, $this->opening->refresh()->status);
    }

    public function test_an_opening_with_several_positions_stays_open_until_all_are_filled(): void
    {
        $this->opening->update(['positions' => 2]);

        $this->service()->hire($this->offered(), now());

        $this->assertSame(OpeningStatus::Open, $this->opening->refresh()->status);

        $this->service()->hire($this->offered(['email' => 'second@example.com']), now());

        $this->assertSame(OpeningStatus::Closed, $this->opening->refresh()->status);
    }

    public function test_nobody_is_hired_beyond_the_positions_or_with_an_email_already_in_use(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $taken = $this->offered(['email' => 'taken@example.com']);

        try {
            $this->service()->hire($taken, now());
            $this->fail('The email is in use already.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $second = $this->offered(['email' => 'second@example.com']);
        $this->service()->hire($this->offered(), now());

        $this->expectException(DomainException::class);
        $this->service()->hire($second, now());
    }

    public function test_an_applicant_without_an_email_can_be_hired(): void
    {
        $profile = $this->service()->hire($this->offered(['email' => null]), now());

        $this->assertNull($profile->user->email);
    }

    public function test_an_applicant_is_rejected_at_any_step_but_not_after_a_decision(): void
    {
        $applicant = $this->offered();

        $this->service()->reject($applicant, 'Declined the offer');

        $this->assertSame([ApplicantStage::Rejected, 'Declined the offer'], [$applicant->stage, $applicant->rejection_reason]);

        $this->expectException(DomainException::class);
        $this->service()->reject($applicant);
    }

    public function test_the_funnel_counts_the_applicants_at_each_step(): void
    {
        $this->applicant(['email' => 'a@example.com']);
        $this->applicant(['email' => 'b@example.com']);
        $this->service()->shortlist($this->applicant(['email' => 'c@example.com']));

        $this->assertSame(
            ['applied' => 2, 'shortlisted' => 1, 'interview' => 0, 'offered' => 0, 'hired' => 0, 'rejected' => 0],
            $this->service()->funnel($this->opening->id),
        );
    }

    public function test_the_front_desk_enters_an_application(): void
    {
        $secretary = $this->actingAsRole('secretary', $this->branch->id);

        Livewire::test(ManageJobApplicants::class)
            ->callAction('create', data: [
                'job_opening_id' => $this->opening->id,
                'firstname' => 'Sara',
                'lastname' => 'Malik',
                'phone' => '0301-5556667',
                'qualification' => 'B.Ed.',
                'experience_years' => 2,
                'expected_salary' => 38000,
            ])
            ->assertHasNoFormErrors();

        $applicant = JobApplicant::query()->firstOrFail();

        $this->assertSame(['Sara MALIK', ApplicantStage::Applied, $this->branch->id, $secretary->id], [$applicant->name, $applicant->stage, $applicant->campus_id, $applicant->recorded_by]);
    }

    public function test_the_applicant_is_taken_through_the_steps_from_the_screen(): void
    {
        $this->actingAsRole('admin');
        $applicant = $this->applicant();

        Livewire::test(ManageJobApplicants::class)
            ->assertActionHidden(TestAction::make('offer')->table($applicant))
            ->callAction(TestAction::make('shortlist')->table($applicant))
            ->callAction(TestAction::make('scheduleInterview')->table($applicant), data: ['interview_at' => '2026-03-10 10:00'])
            ->assertActionHidden(TestAction::make('offer')->table($applicant))
            ->callAction(TestAction::make('recordInterview')->table($applicant), data: ['rating' => 5, 'notes' => 'Excellent demo lesson'])
            ->callAction(TestAction::make('offer')->table($applicant), data: ['salary' => 50000])
            ->callAction(TestAction::make('hire')->table($applicant), data: ['joining_on' => '2026-04-01'])
            ->assertNotified(__('Applicant hired'));

        $this->assertSame(ApplicantStage::Hired, $applicant->refresh()->stage);
        $this->assertSame(50000, EmployeeProfile::query()->findOrFail($applicant->employee_profile_id)->basic_salary);
    }

    public function test_the_front_desk_cannot_hire(): void
    {
        $this->actingAsRole('secretary', $this->branch->id);
        $applicant = $this->offered();

        Livewire::test(ManageJobApplicants::class)
            ->assertActionHidden(TestAction::make('hire')->table($applicant))
            ->assertActionVisible(TestAction::make('reject')->table($applicant));
    }

    public function test_openings_and_applicants_follow_the_branch(): void
    {
        $mine = $this->applicant();
        $otherOpening = JobOpening::factory()->create();
        $theirs = JobApplicant::factory()->create(['job_opening_id' => $otherOpening->id]);
        $this->actingAsRole('secretary', $this->branch->id);

        Livewire::test(ManageJobApplicants::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        Livewire::test(ManageJobOpenings::class)
            ->assertCanSeeTableRecords([$this->opening])
            ->assertCanNotSeeTableRecords([$otherOpening]);
    }

    public function test_an_opening_is_made_from_the_screen(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ManageJobOpenings::class)
            ->callAction('create', data: ['title' => 'Accountant', 'campus_id' => $this->branch->id, 'employment_type' => 'contract', 'positions' => 2, 'status' => 'open'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('job_openings', ['title' => 'Accountant', 'positions' => 2, 'campus_id' => $this->branch->id]);
    }

    public function test_viewers_cannot_open_recruitment(): void
    {
        $this->actingAsRole('viewer');

        $this->assertFalse(JobOpeningResource::canAccess());
        $this->get(JobApplicantResource::getUrl())->assertForbidden();
    }
}
