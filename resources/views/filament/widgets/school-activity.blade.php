@php
    $dots = [
        'admission' => 'bg-sky-500',
        'payment' => 'bg-emerald-500',
        'attendance' => 'bg-teal-500',
        'result' => 'bg-indigo-500',
        'timetable' => 'bg-amber-500',
    ];
@endphp

<x-filament-widgets::widget>
    @if ($events->isEmpty())
        <p class="px-5 pt-1 pb-5 text-sm text-gray-600 dark:text-gray-400">{{ __('Nothing has happened yet.') }}</p>
    @else
        <ol class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/5 dark:border-white/5">
            @foreach ($events as $event)
                <li class="cf-row cf-rise flex gap-3 px-5 py-3" style="animation-delay: {{ 120 + $loop->index * 50 }}ms">
                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dots[$event['kind']] ?? 'bg-gray-400' }}" aria-hidden="true"></span>
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-sm leading-snug text-gray-700 dark:text-gray-300"><b class="font-semibold text-gray-950 dark:text-white">{{ $event['title'] }}</b> {{ $event['detail'] }}</span>
                        <time class="text-xs text-gray-600 dark:text-gray-400" datetime="{{ $event['at']->toIso8601String() }}" title="{{ $event['at']->translatedFormat('j M Y, H:i') }}">{{ $event['at']->diffForHumans() }}</time>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</x-filament-widgets::widget>
