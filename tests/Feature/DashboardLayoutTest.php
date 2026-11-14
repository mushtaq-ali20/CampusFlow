<?php

namespace Tests\Feature;

use App\Filament\Pages\SchoolDashboard;
use App\Filament\Widgets\DashboardKpis;
use App\Models\User;
use App\Models\Year;
use App\Services\DashboardLayout;
use App\Services\Settings;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $year = Year::factory()->create(['name' => '2026-2027', 'start' => '2026-04-01', 'end' => '2027-03-31']);
        app(Settings::class)->set(['current_academic_year' => $year->id]);

        $this->admin = User::factory()->create(['campus_id' => null]);
        $this->admin->assignRole('admin');
    }

    private function schoolOnlyUser(): User
    {
        $user = User::factory()->create(['campus_id' => null]);
        $user->assignRole(Role::findOrCreate('school-only')->givePermissionTo('school.view'));

        return $user;
    }

    /**
     * @return array{pair: list<string>, full: list<string>}
     */
    private function layoutGroups(User $user): array
    {
        return app(DashboardLayout::class)->groups($user->fresh());
    }

    public function test_an_administrator_gets_the_number_cards_then_the_pair_then_the_full_width_widgets(): void
    {
        $this->actingAs($this->admin)
            ->get(SchoolDashboard::getUrl())
            ->assertOk()
            ->assertSeeLivewire(DashboardKpis::class)
            ->assertSeeInOrder([
                'cf-board-pair',
                'id="dashboard-attention-title"',
                'id="dashboard-activity-title"',
                'cf-board-full',
                'id="dashboard-fees-title"',
                'id="dashboard-timetable-title"',
                'id="dashboard-classes-title"',
            ], false)
            ->assertDontSee('id="dashboard-lessons-title"', false)
            ->assertDontSee('id="dashboard-exams-title"', false);
    }

    public function test_staff_who_do_not_see_the_fees_do_not_get_the_fees_widget(): void
    {
        $this->actingAs($this->schoolOnlyUser())
            ->get(SchoolDashboard::getUrl())
            ->assertOk()
            ->assertSee('id="dashboard-classes-title"', false)
            ->assertDontSee('id="dashboard-fees-title"', false);
    }

    public function test_a_dropped_widget_keeps_its_new_place(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SchoolDashboard::class)->call('sortDashboardWidget', 'classes', 0, 'full');

        $this->assertSame(['classes', 'fees', 'timetable'], $this->layoutGroups($this->admin)['full']);

        $this->get(SchoolDashboard::getUrl())->assertSeeInOrder(['id="dashboard-classes-title"', 'id="dashboard-fees-title"'], false);
    }

    public function test_the_drop_position_counts_only_the_widgets_the_user_sees(): void
    {
        $user = $this->schoolOnlyUser();
        $this->actingAs($user);

        // They see timetable and classes in the wide group; dropping classes first puts it before the timetable
        Livewire::test(SchoolDashboard::class)->call('sortDashboardWidget', 'classes', 0, 'full');

        $this->assertSame(['classes', 'timetable', 'fees'], $this->layoutGroups($user)['full']);
    }

    public function test_a_widget_stays_in_its_own_group(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SchoolDashboard::class)
            ->call('sortDashboardWidget', 'fees', 0, 'pair')
            ->call('sortDashboardWidget', 'lessons', 0, 'full');

        $this->assertNull($this->admin->fresh()->dashboard_layout);
    }

    public function test_the_arrow_keys_move_a_widget_one_place(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SchoolDashboard::class)
            ->call('nudgeDashboardWidget', 'activity', -1)
            ->call('nudgeDashboardWidget', 'fees', 1);

        $this->assertSame(['activity', 'attention'], $this->layoutGroups($this->admin)['pair']);
        $this->assertSame(['timetable', 'fees', 'classes'], $this->layoutGroups($this->admin)['full']);
    }

    public function test_a_minimized_widget_stays_minimized_until_it_is_expanded(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SchoolDashboard::class)->call('setDashboardWidgetCollapsed', 'activity', true);

        $this->assertSame(['activity'], app(DashboardLayout::class)->collapsed($this->admin->fresh()));
        $this->get(SchoolDashboard::getUrl())->assertSee('x-data="{ open: false }"', false);

        Livewire::test(SchoolDashboard::class)->call('setDashboardWidgetCollapsed', 'activity', false);

        $this->assertSame([], app(DashboardLayout::class)->collapsed($this->admin->fresh()));
    }

    public function test_reset_layout_brings_back_the_default_order_with_every_widget_expanded(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(SchoolDashboard::class)
            ->call('sortDashboardWidget', 'classes', 0, 'full')
            ->call('setDashboardWidgetCollapsed', 'fees', true)
            ->callAction('resetLayout')
            ->assertRedirect(SchoolDashboard::getUrl());

        $this->assertNull($this->admin->fresh()->dashboard_layout);
    }

    public function test_the_menu_groups_start_closed_on_the_first_page_after_signing_in(): void
    {
        $this->actingAs($this->admin);

        $this->get(SchoolDashboard::getUrl())->assertSee('const collapseAllOnce = false', false);

        event(new Login('web', $this->admin, false));

        $this->get(SchoolDashboard::getUrl())->assertSee('const collapseAllOnce = true', false);
        $this->get(SchoolDashboard::getUrl())->assertSee('const collapseAllOnce = false', false);
    }
}
