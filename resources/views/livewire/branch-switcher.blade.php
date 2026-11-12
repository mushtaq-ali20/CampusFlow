<div class="me-2 flex items-center">
    @if ($isLocked)
        <span
            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30"
            title="{{ __('You work for this branch only.') }}"
        >
            <x-filament::icon icon="heroicon-m-building-office-2" class="h-4 w-4" />
            {{ $active?->name }}
            <x-filament::icon icon="heroicon-m-lock-closed" class="h-3.5 w-3.5" />
        </span>
    @else
        <x-filament::dropdown placement="bottom-end" width="xs">
            <x-slot name="trigger">
                <button
                    type="button"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium ring-1 transition',
                        'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => $active === null,
                        'bg-amber-50 text-amber-800 ring-amber-300 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/40' => $active !== null,
                    ])
                    aria-label="{{ __('Branch') }}"
                >
                    <x-filament::icon icon="heroicon-m-building-office-2" class="h-4 w-4" />
                    <span>{{ $active?->name ?? __('All Branches') }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    wire:click="switchTo(null)"
                    :icon="$active === null ? 'heroicon-m-check' : 'heroicon-m-squares-2x2'"
                    :color="$active === null ? 'primary' : 'gray'"
                >
                    {{ __('All Branches') }}
                </x-filament::dropdown.list.item>

                @foreach ($branches as $branch)
                    <x-filament::dropdown.list.item
                        wire:click="switchTo({{ $branch->id }})"
                        :icon="$active?->is($branch) ? 'heroicon-m-check' : 'heroicon-m-building-office-2'"
                        :color="$active?->is($branch) ? 'primary' : 'gray'"
                    >
                        {{ $branch->name }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
</div>
