@php
    $kinds = [
        'admission' => ['icon' => 'heroicon-o-user-plus', 'tint' => 'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300'],
        'payment' => ['icon' => 'heroicon-o-banknotes', 'tint' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300'],
        'attendance' => ['icon' => 'heroicon-o-clipboard-document-check', 'tint' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300'],
        'result' => ['icon' => 'heroicon-o-trophy', 'tint' => 'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300'],
        'timetable' => ['icon' => 'heroicon-o-calendar-days', 'tint' => 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300'],
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section :heading="__('School activity')" :description="__('What happened lately')" icon="heroicon-o-bell-alert">
        @if ($events->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Nothing has happened yet.') }}</p>
        @else
            <ol class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($events as $event)
                    <li class="art-rise flex gap-3 py-2.5" style="animation-delay: {{ 150 + $loop->index * 70 }}ms">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $kinds[$event['kind']]['tint'] }}">
                            <x-filament::icon :icon="$kinds[$event['kind']]['icon']" class="size-5" />
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-sm leading-snug text-gray-700 dark:text-gray-300"><b class="font-semibold text-gray-950 dark:text-white">{{ $event['title'] }}</b> {{ $event['detail'] }}</span>
                            <time class="text-xs text-gray-600 dark:text-gray-400" datetime="{{ $event['at']->toIso8601String() }}" title="{{ $event['at']->translatedFormat('j M Y, H:i') }}">{{ $event['at']->diffForHumans() }}</time>
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
