@if ($branch)
    <p class="mt-1 flex items-center gap-1.5 text-sm text-amber-700 dark:text-amber-300">
        <x-filament::icon icon="heroicon-m-building-office-2" class="h-4 w-4" />
        {{ __('Showing :branch only', ['branch' => $branch->name]) }}
    </p>
@endif
