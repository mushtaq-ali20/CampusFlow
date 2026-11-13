<?php

namespace App\Services;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Contracts\Support\Htmlable;

/**
 * What the top bar and the page header show about the open screen: the breadcrumb, which starts with the menu
 * group ("Fees › Fee Vouchers"), and the menu icon of the screen.
 */
class PageTrail
{
    /**
     * The breadcrumb of a page: its menu group, then Filament's own breadcrumbs without the closing "List".
     *
     * @param  array<int|string, string|Htmlable>  $breadcrumbs  Filament's breadcrumbs of the page, as URL => label.
     * @return array<int|string, string|Htmlable>
     */
    public static function crumbs(Page $page, array $breadcrumbs): array
    {
        if ($page instanceof ListRecords || $page instanceof ManageRecords) {
            array_pop($breadcrumbs);
        }

        if ($breadcrumbs === []) {
            $breadcrumbs = [$page::getNavigationLabel()];
        }

        $group = self::owner($page)::getNavigationGroup();

        return is_string($group) && filled($group)
            ? [$group, ...$breadcrumbs]
            : $breadcrumbs;
    }

    /**
     * The menu icon of the screen the page belongs to.
     */
    public static function icon(Page $page): string|BackedEnum|Htmlable|null
    {
        return self::owner($page)::getNavigationIcon();
    }

    /**
     * The class that holds the menu entry: the resource of a resource page, or the page itself.
     *
     * @return class-string
     */
    private static function owner(Page $page): string
    {
        return $page instanceof ResourcePage ? $page::getResource() : $page::class;
    }
}
