<x-filament-widgets::widget>
    <div class="flex flex-col gap-6">
        <section class="school-art art-rise relative flex flex-wrap items-stretch overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10" style="background: var(--art-sky)">
            <div class="relative z-10 flex min-w-0 flex-[1_1_22rem] flex-col justify-center gap-3 p-6 sm:p-8">
                <span class="self-start rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-amber-800 dark:border-white/10 dark:bg-gray-900 dark:text-amber-300">
                    {{ $year?->name ?? __('No academic year') }}
                    @if ($schoolDay)
                        · {{ __('Day :day of the school year', ['day' => $schoolDay]) }}
                    @endif
                </span>
                <h2 class="text-2xl font-bold tracking-tight text-balance text-gray-950 sm:text-3xl dark:text-white">
                    {{ $greeting }}@if ($name), {{ $name }}@endif
                </h2>
                <p class="max-w-prose text-sm leading-relaxed text-gray-700 sm:text-base dark:text-gray-300">
                    {{ now()->translatedFormat('l, j F Y') }} ·
                    {{ trans_choice(':count student|:count students', $counts['students'], ['count' => number_format($counts['students'])]) }}
                    {{ trans_choice('at :count branch|across :count branches', $counts['branches'], ['count' => $counts['branches']]) }}.
                </p>
                <div class="flex flex-wrap gap-2">
                    @if ($attendanceUrl)
                        <x-filament::button tag="a" :href="$attendanceUrl" icon="heroicon-m-clipboard-document-check">{{ __('Mark attendance') }}</x-filament::button>
                    @endif
                    @if ($admissionUrl)
                        <x-filament::button tag="a" :href="$admissionUrl" color="gray" icon="heroicon-m-user-plus">{{ __('New admission') }}</x-filament::button>
                    @endif
                </div>
            </div>
            <div class="flex min-w-0 flex-[1_1_30rem] items-end">
                @include('filament.widgets.partials.school-scene')
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <div class="art-lift art-rise flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="animation-delay: {{ $loop->index * 80 }}ms">
                    <div class="grid size-16 shrink-0 place-items-center rounded-xl {{ $card['tint'] }}">
                        @include('filament.widgets.partials.count-art', ['art' => $card['art'], 'delay' => $loop->index * 300])
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $card['label'] }}</span>
                        <span class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ number_format($card['value']) }}</span>
                        <span class="truncate text-xs text-gray-600 dark:text-gray-400" title="{{ $card['note'] }}">{{ $card['note'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
