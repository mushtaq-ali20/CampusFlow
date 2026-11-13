<?php

namespace Tests\Feature;

use App\Filament\Pages\TimetableManager;
use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Filament\Resources\FeeVouchers\Pages\ListFeeVouchers;
use App\Models\AdmissionEnquiry;
use App\Models\Campus;
use App\Models\User;
use App\Services\PageTrail;
use Database\Seeders\PermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create(['campus_id' => null]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
    }

    public function test_the_menu_renders_with_group_icons_and_item_icons(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('fi-sidebar-group-dropdown-trigger-btn', false)
            ->assertSee('fi-sidebar-item-btn', false);
    }

    public function test_every_group_has_an_icon_and_no_two_screens_share_one(): void
    {
        $groupIcons = [];
        $itemIcons = [];

        foreach (Filament::getNavigation() as $group) {
            if (filled($group->getLabel())) {
                $this->assertNotNull($group->getIcon(), "The group {$group->getLabel()} has no icon.");
                $groupIcons[] = $group->getIcon()->name;
            }

            foreach ($group->getItems() as $item) {
                $this->assertNotNull($item->getIcon(), "The screen {$item->getLabel()} has no icon.");
                $itemIcons[$item->getIcon()->name][] = $item->getLabel();
            }
        }

        $this->assertSame($groupIcons, array_unique($groupIcons), 'Two groups share an icon.');
        $this->assertSame([], array_diff($groupIcons, array_diff($groupIcons, array_keys($itemIcons))), 'A group icon is also used by a screen.');

        foreach ($itemIcons as $icon => $labels) {
            $this->assertCount(1, $labels, "The icon {$icon} is used by: ".implode(', ', $labels));
        }
    }

    public function test_the_enquiries_badge_counts_only_enquiries_in_process(): void
    {
        $campus = Campus::factory()->create();
        AdmissionEnquiry::factory()->count(2)->create(['campus_id' => $campus->id]);
        AdmissionEnquiry::factory()->registered()->create(['campus_id' => $campus->id]);

        $this->assertSame('2', AdmissionEnquiryResource::getNavigationBadge());
    }

    public function test_the_enquiries_badge_is_empty_when_nothing_is_in_process(): void
    {
        $this->assertNull(AdmissionEnquiryResource::getNavigationBadge());
    }

    public function test_a_list_page_breadcrumb_starts_with_its_menu_group_and_drops_the_closing_list(): void
    {
        $page = new ListFeeVouchers;

        $crumbs = PageTrail::crumbs($page, $page->getBreadcrumbs());

        $this->assertSame([__('Fees'), FeeVoucherResource::getBreadcrumb()], array_values($crumbs));
        $this->assertSame(FeeVoucherResource::getNavigationIcon(), PageTrail::icon($page));
    }

    public function test_a_page_outside_a_resource_shows_its_group_and_its_own_name(): void
    {
        $page = new TimetableManager;

        $this->assertSame(
            [__('School'), TimetableManager::getNavigationLabel()],
            array_values(PageTrail::crumbs($page, $page->getBreadcrumbs())),
        );
    }

    public function test_the_breadcrumb_is_sent_to_the_top_bar_with_the_screen_icon_beside_the_page_name(): void
    {
        $this->get(FeeVoucherResource::getUrl())
            ->assertOk()
            ->assertSee('id="cf-topbar-trail"', false)
            ->assertSee('x-teleport="#cf-topbar-trail"', false)
            ->assertSee('cf-header-icon', false);
    }
}
