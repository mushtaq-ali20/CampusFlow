<?php

namespace Tests\Feature;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Filament\Resources\Complaints\ComplaintResource;
use App\Filament\Resources\Complaints\Pages\CreateComplaint;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Campus;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ComplaintService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private User $handler;

    private User $principal;

    private ComplaintCategory $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->travelTo('2026-11-11 10:00');

        $this->branch = Campus::factory()->create();
        $this->handler = User::factory()->create(['firstname' => 'Farhan']);
        $this->handler->assignRole('secretary');
        $this->principal = User::factory()->create(['firstname' => 'Principal']);
        $this->principal->assignRole('admin');
        $this->transport = ComplaintCategory::factory()->create([
            'name' => 'Transport',
            'response_hours' => 24,
            'assign_to_id' => $this->handler->id,
            'escalate_to_id' => $this->principal->id,
        ]);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function logComplaint(array $data = []): Complaint
    {
        return app(ComplaintService::class)->create([
            'campus_id' => $this->branch->id,
            'complaint_category_id' => $this->transport->id,
            'priority' => ComplaintPriority::Medium,
            'complainant_type' => 'parent',
            'complainant_name' => 'Imran Khan',
            'subject' => 'Bus came 40 minutes late',
            'description' => 'Third time this week.',
            ...$data,
        ], null);
    }

    public function test_a_complaint_goes_to_the_handler_of_its_category_with_a_time_limit_set_by_its_priority(): void
    {
        $medium = $this->logComplaint();
        $urgent = $this->logComplaint(['priority' => ComplaintPriority::Urgent]);
        $low = $this->logComplaint(['priority' => ComplaintPriority::Low]);

        $this->assertSame('C-2026-0001', $medium->complaint_number);
        $this->assertSame(ComplaintStatus::New, $medium->status);
        $this->assertSame($this->handler->id, $medium->assigned_to);
        $this->assertEquals(now()->addHours(24), $medium->due_at);
        $this->assertEquals(now()->addHours(6), $urgent->due_at);
        $this->assertEquals(now()->addHours(48), $low->due_at);
        $this->assertSame(1, ComplaintPriority::Urgent->hoursFor(2));
        $this->assertCount(3, $this->handler->notifications);
        $this->assertSame('Complaint logged. To be resolved by 12 Nov 2026 10:00.', $medium->updates()->sole()->note);
    }

    public function test_overdue_complaints_are_escalated_with_a_new_time_limit(): void
    {
        $overdue = $this->logComplaint();
        $onTime = $this->logComplaint();
        $resolvedLate = $this->logComplaint();
        app(ComplaintService::class)->resolve($resolvedLate, 'Driver changed', null);
        $onTime->update(['due_at' => now()->addDays(3)]);

        $this->travel(25)->hours();
        $escalated = app(ComplaintService::class)->escalateOverdue();

        $overdue->refresh();
        $this->assertSame(1, $escalated);
        $this->assertSame(ComplaintStatus::Escalated, $overdue->status);
        $this->assertSame(1, $overdue->escalation_level);
        $this->assertSame($this->principal->id, $overdue->assigned_to);
        $this->assertEquals(now()->addHours(24), $overdue->due_at);
        $this->assertSame('Escalated (level 1): the time limit passed.', $overdue->updates()->latest('id')->first()->note);
        $this->assertNull($overdue->updates()->latest('id')->first()->user_id);
        $this->assertCount(1, $this->principal->notifications);
        $this->assertSame(ComplaintStatus::New, $onTime->fresh()->status);
        $this->assertSame(ComplaintStatus::Resolved, $resolvedLate->fresh()->status);
    }

    public function test_the_escalation_runs_every_fifteen_minutes(): void
    {
        $complaint = $this->logComplaint();
        $this->travel(2)->days();

        $this->artisan('campusflow:escalate-complaints')->assertSuccessful();

        $this->assertSame(ComplaintStatus::Escalated, $complaint->fresh()->status);
        $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'campusflow:escalate-complaints'));
        $this->assertSame('*/15 * * * *', $event?->expression);
    }

    public function test_the_front_desk_logs_a_complaint(): void
    {
        $this->actingAsRole('secretary');

        Livewire::test(CreateComplaint::class)
            ->fillForm([
                'campus_id' => $this->branch->id,
                'complaint_category_id' => $this->transport->id,
                'priority' => 'high',
                'complainant_type' => 'parent',
                'complainant_name' => 'Sadia Ahmed',
                'complainant_phone' => '0321-7654321',
                'subject' => 'Van driver rude',
                'description' => 'The driver shouted at the children.',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $complaint = Complaint::query()->sole();
        $this->assertSame($this->handler->id, $complaint->assigned_to);
        $this->assertEquals(now()->addHours(12), $complaint->due_at);
    }

    public function test_the_assignee_works_on_a_complaint_without_the_complaints_permission(): void
    {
        $teacher = Teacher::factory()->create();
        $complaint = $this->logComplaint(['assigned_to' => $teacher->id]);
        $other = $this->logComplaint();
        $this->actingAs($teacher->user);

        $this->assertTrue(ComplaintResource::canAccess());
        Livewire::test(ListComplaints::class)
            ->assertCanSeeTableRecords([$complaint])
            ->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ViewComplaint::class, ['record' => $complaint->id])
            ->assertActionHidden('assign')
            ->callAction('comment', data: ['note' => 'Spoke to the transport manager'])
            ->callAction('resolve', data: ['resolution' => 'New route from Monday'])
            ->assertHasNoActionErrors();

        $complaint->refresh();
        $this->assertSame(ComplaintStatus::Resolved, $complaint->status);
        $this->assertSame('New route from Monday', $complaint->resolution);
        $this->assertSame(['Complaint logged. To be resolved by 12 Nov 2026 10:00.', 'Spoke to the transport manager', 'Resolved: New route from Monday'], $complaint->updates()->orderBy('id')->pluck('note')->all());
    }

    public function test_staff_assign_and_reopen_complaints(): void
    {
        $this->actingAsRole('secretary');
        $teacher = Teacher::factory()->create();
        $complaint = $this->logComplaint();

        Livewire::test(ViewComplaint::class, ['record' => $complaint->id])
            ->callAction('assign', data: ['assigned_to' => $teacher->id, 'note' => 'Class teacher knows the family'])
            ->callAction('resolve', data: ['resolution' => 'Apologised to the parents'])
            ->callAction('reopen', data: ['reason' => 'Happened again'])
            ->assertHasNoActionErrors();

        $complaint->refresh();
        $this->assertSame($teacher->id, $complaint->assigned_to);
        $this->assertSame(ComplaintStatus::InProgress, $complaint->status);
        $this->assertNull($complaint->resolved_at);
        $this->assertCount(2, $teacher->user->notifications);
    }

    public function test_branch_staff_see_the_complaints_of_their_branch(): void
    {
        $ours = $this->logComplaint();
        $theirs = $this->logComplaint(['campus_id' => Campus::factory()->create()->id]);
        $this->actingAsRole('secretary', $this->branch);

        Livewire::test(ListComplaints::class)
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_users_without_the_permission_or_a_complaint_cannot_open_the_complaints(): void
    {
        $this->actingAsRole('viewer');

        $this->assertFalse(ComplaintResource::canAccess());
        $this->get(ComplaintResource::getUrl())->assertForbidden();
    }

    public function test_complaints_are_never_deleted(): void
    {
        $this->actingAsRole('admin');

        $this->assertFalse(ComplaintResource::canDelete($this->logComplaint()));
    }
}
