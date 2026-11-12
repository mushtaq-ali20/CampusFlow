<x-filament-panels::page>
    @php
        $navigation = $this->getCachedSubNavigation();
    @endphp

    @foreach($navigation as $group)
        <x-filament::section>
            @if($group->getLabel())
                <x-slot name="heading">{{ $group->getLabel() }}</x-slot>
            @endif
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($group->getItems() as $item)
                    <a href="{{ $item->getUrl() }}" @class([
                        'flex items-start gap-3 p-4 rounded-lg border transition',
                        'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800',
                    ])>
                        @if($item->getIcon())
                            <x-filament::icon :icon="$item->getIcon()" class="w-6 h-6 text-primary-500 shrink-0 mt-0.5" />
                        @endif
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $item->getLabel() }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
