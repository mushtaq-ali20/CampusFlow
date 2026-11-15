{{--
    One widget of the dashboard board: a handle to drag it (or move it with the arrow keys), its title with the
    filters it uses, a button to minimize it, and its content. Needs: $widget, $titles, $chips, $collapsed, $delay,
    and the filters of the page.
--}}
@php
    use App\Filament\Widgets\ClassExplorer;
    use App\Filament\Widgets\FeeBranchProgress;
    use App\Filament\Widgets\FeeDefaulters;
    use App\Filament\Widgets\FeeStats;
    use App\Filament\Widgets\FeeSummary;
    use App\Filament\Widgets\NeedsAttention;
    use App\Filament\Widgets\SchoolActivity;
    use App\Filament\Widgets\TimetableMonthly;
    use App\Filament\Widgets\TimetableWeekly;

    $isCollapsed = in_array($widget, $collapsed, true);
    $grip = "dashboard-{$widget}-grip";
    $refocus = "\$nextTick(() => document.getElementById('{$grip}')?.focus())";
@endphp

<section
    wire:key="dashboard-panel-{{ $widget }}"
    wire:sort:item="{{ $widget }}"
    x-data="{ open: @js(! $isCollapsed) }"
    class="cf-card cf-panel cf-rise"
    style="animation-delay: {{ $delay }}ms"
    aria-labelledby="dashboard-{{ $widget }}-title"
>
    <header class="cf-panel-head">
        <button
            type="button"
            id="{{ $grip }}"
            wire:sort:handle
            class="cf-icon-btn cf-grip"
            aria-label="{{ __('Move :widget. Drag it, or press the arrow keys.', ['widget' => $titles[$widget]]) }}"
            x-on:keydown.arrow-up.prevent="$wire.nudgeDashboardWidget(@js($widget), -1).then(() => {{ $refocus }})"
            x-on:keydown.arrow-left.prevent="$wire.nudgeDashboardWidget(@js($widget), -1).then(() => {{ $refocus }})"
            x-on:keydown.arrow-down.prevent="$wire.nudgeDashboardWidget(@js($widget), 1).then(() => {{ $refocus }})"
            x-on:keydown.arrow-right.prevent="$wire.nudgeDashboardWidget(@js($widget), 1).then(() => {{ $refocus }})"
        >
            <svg viewBox="0 0 24 24" class="size-4" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6" /><circle cx="15" cy="6" r="1.6" /><circle cx="9" cy="12" r="1.6" /><circle cx="15" cy="12" r="1.6" /><circle cx="9" cy="18" r="1.6" /><circle cx="15" cy="18" r="1.6" /></svg>
        </button>

        <h2 id="dashboard-{{ $widget }}-title" class="cf-panel-title">{{ $titles[$widget] }}</h2>

        @if (filled($chips[$widget] ?? null))
            <div class="cf-panel-chips">
                @foreach ($chips[$widget] as $chip)
                    <span class="cf-chip-label">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        <button
            type="button"
            class="cf-icon-btn ms-auto"
            x-on:click="open = ! open; $wire.setDashboardWidgetCollapsed(@js($widget), ! open)"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            x-bind:aria-label="open ? @js(__('Minimize :widget', ['widget' => $titles[$widget]])) : @js(__('Expand :widget', ['widget' => $titles[$widget]]))"
            aria-controls="dashboard-{{ $widget }}-body"
        >
            <svg viewBox="0 0 24 24" class="size-4 transition-transform duration-200" x-bind:class="open ? '' : '-rotate-90'" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
        </button>
    </header>

    <div id="dashboard-{{ $widget }}-body" class="cf-panel-body" x-show="open" x-collapse @if ($isCollapsed) style="display: none" @endif>
        @switch($widget)
            @case('attention')
                @livewire(NeedsAttention::class, [], key('dashboard-attention'))
                @break

            @case('activity')
                @livewire(SchoolActivity::class, [], key('dashboard-activity'))
                @break

            @case('fees')
                <div class="cf-panel-filters">{{ $this->feeFiltersForm }}</div>
                @livewire(FeeStats::class, ['pageFilters' => $feeFilters], key('dashboard-fee-stats'))
                @if (FeeBranchProgress::canView())
                    @livewire(FeeBranchProgress::class, ['pageFilters' => $feeFilters], key('dashboard-fee-branches'))
                @endif
                @if (FeeSummary::canView())
                    @livewire(FeeSummary::class, ['pageFilters' => $feeFilters], key('dashboard-fee-summary'))
                @endif
                @if (FeeDefaulters::canView())
                    @livewire(FeeDefaulters::class, ['pageFilters' => $feeFilters], key('dashboard-fee-defaulters'))
                @endif
                @break

            @case('timetable')
                <div class="cf-panel-filters">{{ $this->timetableFiltersForm }}</div>
                @if ($isMonthly)
                    @livewire(TimetableMonthly::class, ['pageFilters' => $timetableFilters], key('dashboard-timetable-monthly'))
                @else
                    @livewire(TimetableWeekly::class, ['pageFilters' => $timetableFilters], key('dashboard-timetable-weekly'))
                @endif
                @break

            @case('classes')
                @livewire(ClassExplorer::class, [], key('dashboard-classes'))
                @break
        @endswitch
    </div>
</section>
