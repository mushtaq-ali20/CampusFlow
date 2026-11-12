<?php

namespace Tests\Feature;

use App\Enums\AppraisalStatus;
use App\Filament\Resources\AppraisalCycles\Pages\ManageAppraisalCycles;
use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Filament\Resources\Appraisals\Pages\ManageAppraisals;
use App\Filament\Resources\EvaluationCriteria\EvaluationCriterionResource;
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCriterion;
use App\Models\User;
use App\Services\AppraisalService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppraisalTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private AppraisalCycle $cycle;

    private EvaluationCriterion $teaching;

    private EvaluationCriterion $punctuality;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branch = Campus::factory()->create();
        $this->cycle = AppraisalCycle::factory()->create(['name' => 'Annual 2026']);
        $this->teaching = EvaluationCriterion::factory()->create(['name' => 'Teaching', 'weight' => 3, 'sort' => 1]);
        $this->punctuality = EvaluationCriterion::factory()->create(['name' => 'Punctuality', 'weight' => 1, 'sort' => 2]);
        $this->admin = User::factory()->create(['campus_id' => null]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function employee(string $firstname = 'Sana', array $attributes = []): EmployeeProfile
    {
        $user = User::factory()->create(['firstname' => $firstname, 'lastname' => 'Raza', 'campus_id' => null]);

        return EmployeeProfile::factory()->create(['user_id' => $user->id, 'campus_id' => $this->branch->id, ...$attributes]);
    }

    private function service(): AppraisalService
    {
        return app(AppraisalService::class);
    }

    /**
     * @return array<int, array{score: int, remarks?: string}>
     */
    private function scores(int $teaching, int $punctuality): array
    {
        return [$this->teaching->id => ['score' => $teaching, 'remarks' => 'Well prepared'], $this->punctuality->id => ['score' => $punctuality]];
    }

    private function submitted(?EmployeeProfile $employee = null): Appraisal
    {
        $appraisal = $this->service()->start($this->cycle, $employee ?? $this->employee(), $this->admin);

        return $this->service()->evaluate($appraisal, $this->scores(4, 2), 'Explains well', 'Arrive on time', $this->admin, submit: true);
    }

    public function test_the_score_is_the_weighted_percentage_of_the_highest_score(): void
    {
        $appraisal = $this->submitted();

        $this->assertSame([70.0, AppraisalStatus::Submitted, 'Explains well'], [$appraisal->score, $appraisal->status, $appraisal->strengths]);
        $this->assertSame('Meets expectations', AppraisalService::ratingFor($appraisal->score));
        $this->assertSame('Well prepared', $appraisal->scores->firstWhere('evaluation_criterion_id', $this->teaching->id)->remarks);
    }

    public function test_the_ratings_follow_the_score(): void
    {
        $this->assertSame(
            ['Outstanding', 'Exceeds expectations', 'Meets expectations', 'Needs improvement', 'Unsatisfactory', null],
            array_map(fn (?float $score) => AppraisalService::ratingFor($score), [90.0, 75.0, 60.0, 40.0, 39.9, null]),
        );
    }

    public function test_a_draft_keeps_partial_scores_but_cannot_be_submitted_incomplete(): void
    {
        $appraisal = $this->service()->start($this->cycle, $this->employee(), $this->admin);

        $draft = $this->service()->evaluate($appraisal, [$this->teaching->id => ['score' => 5]], null, null, $this->admin);

        $this->assertSame([AppraisalStatus::Draft, 100.0, 1], [$draft->status, $draft->score, $draft->scores()->count()]);

        $this->expectException(DomainException::class);
        $this->service()->evaluate($draft, [$this->teaching->id => ['score' => 5]], null, null, $this->admin, submit: true);
    }

    public function test_scores_outside_1_to_5_and_criteria_not_in_use_are_refused_or_ignored(): void
    {
        $old = EvaluationCriterion::factory()->create(['is_active' => false]);
        $appraisal = $this->service()->start($this->cycle, $this->employee(), $this->admin);

        $saved = $this->service()->evaluate($appraisal, $this->scores(3, 3) + [$old->id => ['score' => 5]], null, null, $this->admin);

        $this->assertSame(2, $saved->scores()->count());

        $this->expectException(DomainException::class);
        $this->service()->evaluate($appraisal, $this->scores(6, 3), null, null, $this->admin);
    }

    public function test_an_employee_has_one_evaluation_in_a_cycle(): void
    {
        $sana = $this->employee();
        $this->service()->start($this->cycle, $sana, $this->admin);

        $this->expectException(DomainException::class);
        $this->service()->start($this->cycle, $sana, $this->admin);
    }

    public function test_starting_a_cycle_covers_the_working_employees_of_the_branch_once(): void
    {
        $sana = $this->employee();
        $this->employee('Hina');
        $this->employee('Gone', ['status' => 'left', 'left_on' => '2026-01-31']);
        EmployeeProfile::factory()->create();
        $this->service()->start($this->cycle, $sana, $this->admin);

        $this->assertSame(1, $this->service()->startForBranch($this->cycle, $this->branch->id, $this->admin));
        $this->assertSame(0, $this->service()->startForBranch($this->cycle, $this->branch->id, $this->admin));
        $this->assertSame(2, Appraisal::query()->count());
    }

    public function test_only_the_employee_acknowledges_a_submitted_evaluation(): void
    {
        $sana = $this->employee();
        $appraisal = $this->submitted($sana);

        try {
            $this->service()->acknowledge($appraisal, $this->admin);
            $this->fail('Only the employee acknowledges.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $acknowledged = $this->service()->acknowledge($appraisal, $sana->user, 'Thank you');

        $this->assertSame([AppraisalStatus::Acknowledged, 'Thank you'], [$acknowledged->status, $acknowledged->employee_comment]);

        $this->expectException(DomainException::class);
        $this->service()->reopen($acknowledged);
    }

    public function test_a_submitted_evaluation_is_reopened_to_correct_it(): void
    {
        $appraisal = $this->service()->reopen($this->submitted());

        $this->assertSame(AppraisalStatus::Draft, $appraisal->status);

        $again = $this->service()->evaluate($appraisal, $this->scores(5, 5), null, null, $this->admin, submit: true);

        $this->assertSame(100.0, $again->score);
    }

    public function test_a_closed_cycle_is_fixed(): void
    {
        $sana = $this->employee();
        $appraisal = $this->service()->start($this->cycle, $sana, $this->admin);
        $this->cycle->update(['is_closed' => true]);

        foreach ([
            fn () => $this->service()->evaluate($appraisal->refresh(), $this->scores(4, 4), null, null, $this->admin),
            fn () => $this->service()->start($this->cycle, $this->employee('Hina'), $this->admin),
            fn () => $this->service()->startForBranch($this->cycle, null, $this->admin),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A closed cycle cannot change.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_evaluation_is_filled_in_and_submitted_from_the_screen(): void
    {
        $appraisal = $this->service()->start($this->cycle, $this->employee(), $this->admin);

        Livewire::test(ManageAppraisals::class)
            ->callAction(TestAction::make('evaluate')->table($appraisal), data: [
                'scores' => $this->scores(5, 4),
                'strengths' => 'Organised',
                'submit' => true,
            ])
            ->assertNotified(__('Evaluation submitted'))
            ->assertActionHidden(TestAction::make('evaluate')->table($appraisal))
            ->assertActionVisible(TestAction::make('read')->table($appraisal));

        $this->assertSame([AppraisalStatus::Submitted, 95.0], [$appraisal->refresh()->status, $appraisal->score]);
    }

    public function test_an_incomplete_evaluation_is_not_submitted_from_the_screen(): void
    {
        $appraisal = $this->service()->start($this->cycle, $this->employee(), $this->admin);

        Livewire::test(ManageAppraisals::class)
            ->callAction(TestAction::make('evaluate')->table($appraisal), data: ['scores' => [$this->teaching->id => ['score' => 5]], 'submit' => true])
            ->assertNotified(__('Give a score on every criterion before submitting.'));

        $this->assertSame(AppraisalStatus::Draft, $appraisal->refresh()->status);
    }

    public function test_evaluations_are_started_from_the_screens(): void
    {
        $sana = $this->employee();
        $this->employee('Hina');

        Livewire::test(ManageAppraisals::class)
            ->callAction('start', data: ['appraisal_cycle_id' => $this->cycle->id, 'employee_profile_id' => $sana->id])
            ->assertNotified(__('Evaluation started'));

        Livewire::test(ManageAppraisalCycles::class)
            ->callAction(TestAction::make('start')->table($this->cycle));

        $this->assertSame(2, Appraisal::query()->where('appraisal_cycle_id', $this->cycle->id)->count());
    }

    public function test_employees_see_and_acknowledge_only_their_own_submitted_evaluation(): void
    {
        $sana = $this->employee();
        $mine = $this->submitted($sana);
        $theirs = $this->submitted($this->employee('Hina'));
        $otherCycle = AppraisalCycle::factory()->create();
        $draft = $this->service()->start($otherCycle, $sana, $this->admin);
        $this->actingAs($sana->user);

        $this->assertTrue(AppraisalResource::canAccess());

        Livewire::test(ManageAppraisals::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $draft])
            ->assertActionHidden('start')
            ->assertActionHidden(TestAction::make('reopen')->table($mine))
            ->callAction(TestAction::make('acknowledge')->table($mine), data: ['comment' => 'Noted'])
            ->assertNotified(__('Evaluation acknowledged'));

        $this->assertSame(AppraisalStatus::Acknowledged, $mine->refresh()->status);
    }

    public function test_staff_see_the_evaluations_of_their_branch(): void
    {
        $mine = $this->submitted();
        $theirs = Appraisal::factory()->create(['appraisal_cycle_id' => $this->cycle->id]);
        $hr = User::factory()->create(['campus_id' => $this->branch->id]);
        $hr->givePermissionTo('appraisals.view');
        $this->actingAs($hr);

        Livewire::test(ManageAppraisals::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertActionHidden(TestAction::make('reopen')->table($mine));
    }

    public function test_the_set_up_screens_are_for_staff_who_see_the_evaluations(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole('secretary');
        $this->actingAs($secretary);

        $this->assertFalse(EvaluationCriterionResource::canAccess());
        $this->assertFalse(AppraisalResource::canAccess());
        $this->get(EvaluationCriterionResource::getUrl())->assertForbidden();
    }
}
