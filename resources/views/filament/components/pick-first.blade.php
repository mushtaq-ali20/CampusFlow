{{--
    The prompt shown under the filter bar of a pick-then-work screen until the choices are made.
--}}
<div class="cf-pick-first">
    <span class="cf-pick-first-icon">
        <x-filament::icon :icon="$icon ?? \Filament\Support\Icons\Heroicon::OutlinedCursorArrowRays" />
    </span>
    <h3>{{ $heading }}</h3>
    <p>{{ $description }}</p>
</div>
