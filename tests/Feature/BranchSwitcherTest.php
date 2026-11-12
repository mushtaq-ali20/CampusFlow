<?php

namespace Tests\Feature;

use App\Enums\Shift;
use App\Filament\Pages\CalendarCombined;
use App\Filament\Pages\HrDashboard;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\FeeStructures\Pages\ManageFeeStructures;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Leaves\Pages\ListLeaves;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Filament\Resources\Settings\Coupons\CouponResource;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Livewire\BranchSwitcher;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\Contact;
use App\Models\FeeStructure;
use App\Models\Leave;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Models\Year;
use App\Services\ActiveBranch;
use App\Services\Settings;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branchOne;

    private Campus $branchTwo;

    private Year $year;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branchOne = Campus::factory()->create(['name' => 'Branch 1']);
        $this->branchTwo = Campus::factory()->create(['name' => 'Branch 2']);
        $this->year = Year::factory()->create(['name' => '2026-2027', 'start' => '2026-04-01', 'end' => '2027-03-31']);
        app(Settings::class)->set(['current_academic_year' => $this->year->id]);

        $this->admin = User::factory()->create(['campus_id' => null]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function chooseBranch(?Campus $branch): void
    {
        app(ActiveBranch::class)->set($branch?->id);
    }

    private function section(Campus $branch, string $name = 'A', Shift $shift = Shift::Morning, ?SchoolClass $class = null): Section
    {
        return Section::factory()->create([
            'campus_id' => $branch->id,
            'school_class_id' => ($class ?? SchoolClass::factory()->create())->id,
            'year_id' => $this->year->id,
            'name' => $name,
            'shift' => $shift,
        ]);
    }

    private function admittedStudent(Section $section): Student
    {
        $student = Student::factory()->create();
        Admission::factory()->create(['student_id' => $student->id, 'section_id' => $section->id]);

        return $student;
    }

    public function test_all_branches_is_the_default(): void
    {
        $this->assertNull(app(ActiveBranch::class)->id());
        $this->assertNull($this->admin->activeBranchId());
    }

    public function test_choosing_a_branch_makes_it_the_active_branch_and_reloads_the_page(): void
    {
        Livewire::test(BranchSwitcher::class)
            ->assertSee('All Branches')
            ->assertSee('Branch 1')
            ->assertSee('Branch 2')
            ->call('switchTo', $this->branchTwo->id)
            ->assertRedirect();

        $this->assertSame($this->branchTwo->id, session(ActiveBranch::SESSION_KEY));
        app(ActiveBranch::class)->forgetResolved();
        $this->assertSame($this->branchTwo->id, $this->admin->activeBranchId());

        Livewire::test(BranchSwitcher::class)->call('switchTo', null);

        app(ActiveBranch::class)->forgetResolved();
        $this->assertNull($this->admin->activeBranchId());
    }

    public function test_an_unknown_branch_means_all_branches(): void
    {
        $this->chooseBranch($this->branchOne);
        app(ActiveBranch::class)->set(999);

        $this->assertNull(app(ActiveBranch::class)->id());
    }

    public function test_signing_in_starts_on_all_branches(): void
    {
        $this->chooseBranch($this->branchOne);
        $this->assertSame($this->branchOne->id, app(ActiveBranch::class)->id());

        event(new Login('web', $this->admin, false));

        $this->assertNull(app(ActiveBranch::class)->id());
        $this->assertNull(session(ActiveBranch::SESSION_KEY));
    }

    public function test_branch_staff_work_on_their_branch_and_cannot_switch(): void
    {
        $secretary = User::factory()->create(['campus_id' => $this->branchOne->id]);
        $secretary->assignRole('secretary');
        $this->actingAs($secretary);

        $this->assertTrue(app(ActiveBranch::class)->isLocked());
        $this->assertSame($this->branchOne->id, $secretary->activeBranchId());

        Livewire::test(BranchSwitcher::class)
            ->assertSee('Branch 1')
            ->assertDontSee('All Branches')
            ->call('switchTo', $this->branchTwo->id);

        app(ActiveBranch::class)->forgetResolved();
        $this->assertSame($this->branchOne->id, $secretary->activeBranchId());
    }

    public function test_the_switcher_is_in_the_top_bar(): void
    {
        $this->get('/admin')
            ->assertSuccessful()
            ->assertSeeLivewire(BranchSwitcher::class);
    }

    public function test_branch_records_follow_the_active_branch(): void
    {
        $first = $this->section($this->branchOne);
        $second = $this->section($this->branchTwo);

        Livewire::test(ListSections::class)->assertCanSeeTableRecords([$first, $second]);

        $this->chooseBranch($this->branchOne);

        Livewire::test(ListSections::class)
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);
    }

    public function test_a_branch_filtered_screen_says_which_branch_it_shows(): void
    {
        $this->get(ListStudents::getUrl())->assertDontSee('Showing Branch 1 only');

        $this->chooseBranch($this->branchOne);

        $this->get(ListStudents::getUrl())->assertSee('Showing Branch 1 only');
    }

    public function test_students_are_listed_with_their_admission_and_follow_the_active_branch(): void
    {
        $class = SchoolClass::factory()->create(['name' => 'Class 1']);
        $inBranchOne = $this->admittedStudent($this->section($this->branchOne, 'B', Shift::Afternoon, $class));
        $inBranchTwo = $this->admittedStudent($this->section($this->branchTwo, 'A', Shift::Morning, $class));
        $notAdmitted = Student::factory()->create();
        Contact::factory()->create(['student_id' => $inBranchOne->id, 'firstname' => 'Tariq', 'lastname' => 'Mehmood']);

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$inBranchOne, $inBranchTwo, $notAdmitted])
            ->assertTableColumnVisible('latestAdmission.campus.name')
            ->assertTableColumnStateSet('latestAdmission.schoolClass.name', 'Class 1', $inBranchOne)
            ->assertTableColumnStateSet('latestAdmission.section.name', 'B', $inBranchOne)
            ->assertTableColumnStateSet('latestAdmission.section.shift', Shift::Afternoon, $inBranchOne)
            ->assertTableColumnStateSet('guardian', 'Tariq Mehmood', $inBranchOne);

        $this->chooseBranch($this->branchOne);

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$inBranchOne])
            ->assertCanNotSeeTableRecords([$inBranchTwo, $notAdmitted])
            ->assertTableColumnHidden('latestAdmission.campus.name');
    }

    public function test_students_are_filtered_by_class_shift_and_section(): void
    {
        $class = SchoolClass::factory()->create();
        $morningSection = $this->section($this->branchOne, 'A', Shift::Morning, $class);
        $afternoonSection = $this->section($this->branchOne, 'A', Shift::Afternoon, $class);
        $morning = $this->admittedStudent($morningSection);
        $afternoon = $this->admittedStudent($afternoonSection);
        $otherClass = $this->admittedStudent($this->section($this->branchOne));

        Livewire::test(ListStudents::class)
            ->filterTable('school_class_id', $class->id)
            ->assertCanSeeTableRecords([$morning, $afternoon])
            ->assertCanNotSeeTableRecords([$otherClass])
            ->filterTable('shift', Shift::Afternoon->value)
            ->assertCanSeeTableRecords([$afternoon])
            ->assertCanNotSeeTableRecords([$morning]);

        Livewire::test(ListStudents::class)
            ->filterTable('section_id', $morningSection->id)
            ->assertCanSeeTableRecords([$morning])
            ->assertCanNotSeeTableRecords([$afternoon, $otherClass]);
    }

    public function test_teachers_of_the_active_branch_are_listed_with_their_subjects_and_sections(): void
    {
        $both = Teacher::factory()->create();
        $both->campuses()->attach([$this->branchOne->id, $this->branchTwo->id]);
        $onlyTwo = Teacher::factory()->create();
        $onlyTwo->campuses()->attach($this->branchTwo->id);

        $class = SchoolClass::factory()->create(['name' => 'Class 3']);
        $english = Subject::factory()->create(['name' => 'English']);
        $sectionOne = $this->section($this->branchOne, 'A', Shift::Morning, $class);
        $sectionTwo = $this->section($this->branchTwo, 'B', Shift::Afternoon, $class);
        TeachingAssignment::factory()->create(['section_id' => $sectionOne->id, 'subject_id' => $english->id, 'teacher_id' => $both->id]);
        TeachingAssignment::factory()->create(['section_id' => $sectionTwo->id, 'subject_id' => $english->id, 'teacher_id' => $both->id]);

        Livewire::test(ListTeachers::class)
            ->assertCanSeeTableRecords([$both, $onlyTwo])
            ->assertTableColumnStateSet('subjects', ['English'], $both)
            ->assertTableColumnStateSet('sections_taught', ['Class 3 - A (Morning)', 'Class 3 - B (Afternoon)'], $both);

        $this->chooseBranch($this->branchOne);

        Livewire::test(ListTeachers::class)
            ->assertCanSeeTableRecords([$both])
            ->assertCanNotSeeTableRecords([$onlyTwo])
            ->assertTableColumnStateSet('sections_taught', ['Class 3 - A (Morning)'], $both);
    }

    public function test_staff_of_the_active_branch_and_staff_of_all_branches_are_listed(): void
    {
        $secretaryOne = User::factory()->create(['campus_id' => $this->branchOne->id]);
        $secretaryOne->assignRole('secretary');
        $secretaryTwo = User::factory()->create(['campus_id' => $this->branchTwo->id]);
        $secretaryTwo->assignRole('secretary');

        $this->chooseBranch($this->branchOne);

        Livewire::test(ManageStaff::class)
            ->assertCanSeeTableRecords([$secretaryOne, $this->admin])
            ->assertCanNotSeeTableRecords([$secretaryTwo]);
    }

    public function test_leaves_follow_the_branches_of_the_teachers(): void
    {
        $teacherOne = Teacher::factory()->create();
        $teacherOne->campuses()->attach($this->branchOne->id);
        $teacherTwo = Teacher::factory()->create();
        $teacherTwo->campuses()->attach($this->branchTwo->id);
        $leaveOne = Leave::factory()->create(['teacher_id' => $teacherOne->id]);
        $leaveTwo = Leave::factory()->create(['teacher_id' => $teacherTwo->id]);

        $this->chooseBranch($this->branchTwo);

        Livewire::test(ListLeaves::class)
            ->assertCanSeeTableRecords([$leaveTwo])
            ->assertCanNotSeeTableRecords([$leaveOne]);
    }

    public function test_fee_structures_of_the_active_branch_include_those_of_all_branches(): void
    {
        $general = FeeStructure::factory()->create(['year_id' => $this->year->id, 'campus_id' => null]);
        $branchOne = FeeStructure::factory()->create(['year_id' => $this->year->id, 'campus_id' => $this->branchOne->id]);
        $branchTwo = FeeStructure::factory()->create(['year_id' => $this->year->id, 'campus_id' => $this->branchTwo->id]);

        $this->chooseBranch($this->branchOne);

        Livewire::test(ManageFeeStructures::class)
            ->assertCanSeeTableRecords([$general, $branchOne])
            ->assertCanNotSeeTableRecords([$branchTwo]);
    }

    public function test_the_language_school_screens_are_left_out_of_the_menu(): void
    {
        foreach ([EnrollmentResource::class, InvoiceResource::class, CouponResource::class, HrDashboard::class, CalendarCombined::class] as $screen) {
            $this->assertFalse($screen::shouldRegisterNavigation(), $screen);
        }
    }
}
