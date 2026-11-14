<x-filament-panels::page>
    @php
        $navigation = $this->getCachedSubNavigation();
        $groups = collect($navigation)->filter(fn ($group) => count($group->getItems()) > 0)->values();
    @endphp

    {{-- The tiles below are this screen's menu, so the tab bar Filament adds above them is hidden here. --}}
    <style>
        .fi-page-sub-navigation-tabs { display: none; }
    </style>

    <div x-data="{ group: 'all' }" class="flex flex-col gap-4">
        @if ($groups->count() > 1)
            <div class="flex flex-wrap gap-1 self-start rounded-[10px] border border-gray-200 bg-white p-1 dark:border-white/10 dark:bg-white/5">
                <button
                    type="button"
                    x-on:click="group = 'all'"
                    x-bind:class="group === 'all' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'text-gray-600 dark:text-gray-300'"
                    class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold"
                >
                    {{ __('All') }}
                </button>
                @foreach ($groups as $index => $group)
                    <button
                        type="button"
                        x-on:click="group = '{{ $index }}'"
                        x-bind:class="group === '{{ $index }}' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'text-gray-600 dark:text-gray-300'"
                        class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold"
                    >
                        {{ $group->getLabel() ?: __('General') }}
                    </button>
                @endforeach
            </div>
        @endif

        @foreach ($groups as $index => $group)
            <section x-show="group === 'all' || group === '{{ $index }}'" class="flex flex-col gap-3">
                @if ($group->getLabel())
                    <h2 class="text-[13px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group->getLabel() }}</h2>
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($group->getItems() as $item)
                        <a
                            href="{{ $item->getUrl() }}"
                            class="flex items-center gap-3.5 rounded-xl border border-gray-200 bg-white px-[18px] py-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-white/5"
                        >
                            <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                @if ($item->getIcon())
                                    <x-filament::icon :icon="$item->getIcon()" class="size-5" />
                                @endif
                            </span>
                            <span class="text-[14.5px] font-semibold text-gray-900 dark:text-white">{{ $item->getLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
