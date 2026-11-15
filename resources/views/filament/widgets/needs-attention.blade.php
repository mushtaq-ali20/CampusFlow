@php
    $dots = [
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-600',
        'sky' => 'bg-sky-500',
        'gray' => 'bg-gray-400',
    ];
@endphp

<x-filament-widgets::widget>
    @if ($items === [])
        <div class="flex items-center gap-3 px-5 pt-1 pb-5 text-sm text-gray-600 dark:text-gray-400">
            <span class="grid size-8 place-items-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">
                <x-filament::icon icon="heroicon-m-check" class="size-4" />
            </span>
            {{ __('All caught up. Nothing is waiting for you.') }}
        </div>
    @else
        <ul class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/5 dark:border-white/5">
            @foreach ($items as $item)
                <li class="cf-row cf-rise flex items-center gap-3 px-5 py-3" style="animation-delay: {{ 120 + $loop->index * 60 }}ms">
                    <span class="size-2 shrink-0 rounded-full {{ $dots[$item['tone']] }}" aria-hidden="true"></span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</span>
                        <span class="truncate text-xs text-gray-600 dark:text-gray-400">{{ $item['note'] }}</span>
                    </span>
                    @if ($item['url'])
                        <x-filament::button tag="a" :href="$item['url']" color="gray" size="sm" class="shrink-0">{{ $item['action'] }}</x-filament::button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-filament-widgets::widget>
