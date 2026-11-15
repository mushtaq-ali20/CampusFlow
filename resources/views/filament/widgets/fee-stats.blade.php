@php
    $tones = [
        'gray' => 'text-gray-600 dark:text-gray-400',
        'success' => 'font-semibold text-emerald-700 dark:text-emerald-400',
        'danger' => 'font-semibold text-rose-700 dark:text-rose-400',
    ];
@endphp

<x-filament-widgets::widget>
    <dl class="cf-fee-stats border-t border-gray-100 dark:border-white/5">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-1 px-5 py-4">
                <dt class="text-xs text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</dt>
                <dd class="text-xl font-bold whitespace-nowrap text-gray-950 tabular-nums dark:text-white">{{ $stat['value'] }}</dd>
                <dd class="text-xs {{ $tones[$stat['tone']] }}">{{ $stat['note'] }}</dd>
            </div>
        @endforeach
    </dl>
</x-filament-widgets::widget>
