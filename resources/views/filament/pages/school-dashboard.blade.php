@php
    use App\Filament\Widgets\DashboardKpis;

    $titles = [
        'attention' => __('Needs attention'),
        'activity' => __('School activity'),
        'fees' => __('Fees'),
        'timetable' => __('Timetable'),
        'classes' => __('Classes'),
    ];
    $chips = ['fees' => $feeChips, 'timetable' => $timetableChips];
@endphp

<x-filament-panels::page class="cf-dashboard">
    @if (DashboardKpis::canView())
        @livewire(DashboardKpis::class, [], key('dashboard-kpis'))
    @endif

    <p class="cf-board-hint">
        <x-filament::icon icon="heroicon-m-arrows-up-down" class="size-4" />
        {{ __('Drag a widget by its handle to reorder it, or use the arrow to minimize it.') }}
    </p>

    @foreach ($groups as $group => $widgets)
        @if ($widgets !== [])
            <div
                @class(['cf-board-group', 'cf-board-pair' => $group === 'pair', 'cf-board-full' => $group === 'full', 'cf-board-single' => count($widgets) === 1])
                wire:sort.ghost.renderless="sortDashboardWidget"
                wire:sort:group-id="{{ $group }}"
            >
                @foreach ($widgets as $widget)
                    @include('filament.pages.partials.dashboard-panel', ['delay' => ($group === 'full' ? 360 : 200) + $loop->index * 80])
                @endforeach
            </div>
        @endif
    @endforeach
</x-filament-panels::page>
