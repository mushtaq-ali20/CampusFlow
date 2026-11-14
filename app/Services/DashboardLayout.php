<?php

namespace App\Services;

use App\Models\User;

/**
 * The widgets of the dashboard board, in two groups: a pair shown side by side (what needs attention and the school
 * activity), and the wide widgets shown one under another across the full width (fees, timetable and classes).
 * Each user can reorder the widgets of a group and minimize any of them; this is kept in users.dashboard_layout as
 * {"groups": {"pair": [...], "full": [...]}, "collapsed": [...]}. A widget stays in its own group.
 */
class DashboardLayout
{
    public const PAIR = 'pair';

    public const FULL = 'full';

    /**
     * Every widget of the board with its group, in the default order.
     *
     * @var array<string, string>
     */
    public const WIDGETS = [
        'attention' => self::PAIR,
        'activity' => self::PAIR,
        'fees' => self::FULL,
        'timetable' => self::FULL,
        'classes' => self::FULL,
    ];

    /**
     * The widgets of each group in the user's order. Widgets added since they saved their layout come last.
     *
     * @return array{pair: list<string>, full: list<string>}
     */
    public function groups(?User $user): array
    {
        $saved = $user?->dashboard_layout['groups'] ?? [];
        $groups = [];

        foreach ([self::PAIR, self::FULL] as $group) {
            $widgets = array_keys(array_filter(self::WIDGETS, fn (string $home): bool => $home === $group));
            $order = array_values(array_intersect(is_array($saved[$group] ?? null) ? $saved[$group] : [], $widgets));

            $groups[$group] = array_values(array_unique([...$order, ...$widgets]));
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function collapsed(?User $user): array
    {
        $saved = $user?->dashboard_layout['collapsed'] ?? [];

        return array_values(array_intersect(is_array($saved) ? $saved : [], array_keys(self::WIDGETS)));
    }

    /**
     * Moves a widget to a position among the widgets the user sees in its group. Widgets they do not see keep
     * their place after the visible ones.
     *
     * @param  list<string>  $visible
     */
    public function move(User $user, string $widget, int $position, string $group, array $visible): void
    {
        if ((self::WIDGETS[$widget] ?? null) !== $group) {
            return;
        }

        $order = $this->groups($user)[$group];
        $shown = array_values(array_filter($order, fn (string $id): bool => in_array($id, $visible, true) && $id !== $widget));

        array_splice($shown, max(0, min($position, count($shown))), 0, [$widget]);

        $hidden = array_values(array_diff($order, $shown));

        $this->save($user, [
            'groups' => [...$this->groups($user), $group => [...$shown, ...$hidden]],
            'collapsed' => $this->collapsed($user),
        ]);
    }

    public function setCollapsed(User $user, string $widget, bool $collapsed): void
    {
        if (! array_key_exists($widget, self::WIDGETS)) {
            return;
        }

        $current = array_values(array_diff($this->collapsed($user), [$widget]));

        $this->save($user, [
            'groups' => $this->groups($user),
            'collapsed' => $collapsed ? [...$current, $widget] : $current,
        ]);
    }

    public function reset(User $user): void
    {
        $user->forceFill(['dashboard_layout' => null])->saveQuietly();
    }

    /**
     * @param  array{groups: array{pair: list<string>, full: list<string>}, collapsed: list<string>}  $layout
     */
    private function save(User $user, array $layout): void
    {
        $user->forceFill(['dashboard_layout' => $layout])->saveQuietly();
    }
}
