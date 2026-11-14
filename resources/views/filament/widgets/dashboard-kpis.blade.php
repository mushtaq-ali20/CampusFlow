<x-filament-widgets::widget>
    <div class="cf-kpis">
        @foreach ($cards as $card)
            <section class="cf-card cf-kpi cf-rise" style="animation-delay: {{ 60 + $loop->index * 70 }}ms" aria-label="{{ $card['label'] }}">
                <div class="flex items-baseline justify-between gap-2">
                    <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $card['label'] }}</h3>
                    @if (filled($card['corner']))
                        <span class="truncate text-xs text-gray-600 dark:text-gray-400">{{ $card['corner'] }}</span>
                    @endif
                </div>

                <p class="text-3xl leading-9 font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                    @include('filament.components.dashboard-count', $card)
                </p>

                <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                    @foreach ($card['segments'] as $segment)
                        <span class="cf-grow h-full rounded-full {{ $segment['class'] }}" style="width: {{ round(min(100, max(0, $segment['width'])), 1) }}%; animation-delay: {{ 200 + $loop->parent->index * 70 }}ms"></span>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="truncate text-gray-600 dark:text-gray-400" title="{{ $card['note'] }}">{{ $card['note'] }}</span>
                    @if ($card['url'])
                        <a href="{{ $card['url'] }}" class="cf-link shrink-0">{{ __('Open') }}</a>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-filament-widgets::widget>
