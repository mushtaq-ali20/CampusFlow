@php
    $tint = [
        'sky' => 'bg-sky-50 text-sky-800 dark:bg-sky-400/10 dark:text-sky-300',
        'violet' => 'bg-violet-50 text-violet-800 dark:bg-violet-400/10 dark:text-violet-300',
        'emerald' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300',
    ];
    $card = 'art-lift art-rise flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
@endphp

<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 {{ $showFees ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        {{-- Attendance --}}
        <div class="{{ $card }}" style="animation-delay: 100ms">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Attendance') }}</h3>
                <span class="text-xs text-gray-600 dark:text-gray-400">
                    {{ $attendance['date'] === null ? __('Not marked yet') : ($attendance['isToday'] ? __('Today') : $attendance['date']->translatedFormat('D, j M')) }}
                </span>
            </div>
            <div class="flex items-center gap-5">
                <svg viewBox="0 0 100 100" class="size-26 shrink-0" role="img" aria-label="{{ __(':rate% attended', ['rate' => $attendance['rate']]) }}">
                    <circle cx="50" cy="50" r="42" fill="none" class="stroke-gray-200 dark:stroke-white/10" stroke-width="10" />
                    <circle class="art-ring stroke-emerald-600 dark:stroke-emerald-400" cx="50" cy="50" r="42" fill="none" stroke-width="10" stroke-linecap="round" stroke-dasharray="264" stroke-dashoffset="{{ $ringOffset }}" transform="rotate(-90 50 50)" />
                    <text x="50" y="56" text-anchor="middle" font-size="19" font-weight="700" class="fill-gray-950 dark:fill-white">{{ round($attendance['rate']) }}%</text>
                </svg>
                <dl class="flex flex-col gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                    <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-emerald-600 dark:bg-emerald-400"></span><dt>{{ __('Present') }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $attendance['present'] }}</dd></div>
                    <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-amber-500"></span><dt>{{ __('Late') }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $attendance['late'] }}</dd></div>
                    <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-rose-600 dark:bg-rose-400"></span><dt>{{ __('Absent') }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $attendance['absent'] }}</dd></div>
                    @if ($attendance['leave'] > 0)
                        <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-sky-600 dark:bg-sky-400"></span><dt>{{ __('Leave') }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $attendance['leave'] }}</dd></div>
                    @endif
                </dl>
            </div>
            <div class="mt-auto flex items-center justify-between gap-2 text-xs text-gray-600 dark:text-gray-400">
                <span>{{ trans_choice(':count section not marked today|:count sections not marked today', $attendance['unmarkedToday'], ['count' => $attendance['unmarkedToday']]) }}</span>
                @if ($attendanceUrl && $attendance['unmarkedToday'] > 0)
                    <x-filament::link :href="$attendanceUrl" size="sm">{{ __('Mark') }}</x-filament::link>
                @endif
            </div>
        </div>

        {{-- Lessons going on --}}
        <div class="{{ $card }}" style="animation-delay: 200ms">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $lessons['state'] === 'next' ? __('Next lessons') : __('In class now') }}</h3>
                @if ($lessons['period'])
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $lessons['state'] === 'now' ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400' }}">
                        @if ($lessons['state'] === 'now')
                            <span class="art-live size-2 rounded-full bg-emerald-600"></span>
                        @endif
                        {{ $lessons['period']->name }} · {{ $lessons['period']->timeRange() }}
                    </span>
                @endif
            </div>
            @if ($lessons['state'] === 'none')
                <div class="flex flex-1 flex-col items-center justify-center gap-2 py-4 text-center">
                    <svg viewBox="0 0 48 48" class="art-bob-slow size-12" aria-hidden="true">
                        <circle cx="24" cy="24" r="18" fill="#fde68a" />
                        <path d="M24 12v12l8 5" stroke="#92400e" stroke-width="3" stroke-linecap="round" fill="none" />
                    </svg>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No more lessons today.') }}</p>
                </div>
            @else
                <ul class="flex flex-col gap-2">
                    @foreach ($lessons['lessons'] as $lesson)
                        @php($tone = $tones[$loop->index % count($tones)])
                        <li class="flex items-center gap-3 rounded-lg px-2.5 py-2 {{ $tint[$tone] }}">
                            <span class="grid size-8 shrink-0 place-items-center rounded-md bg-white text-xs font-bold dark:bg-gray-900">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($lesson->subject?->name ?? '', 0, 2)) }}</span>
                            <span class="flex min-w-0 flex-col">
                                <span class="truncate text-sm font-semibold">{{ $lesson->subject?->name }} · {{ $lesson->section?->full_name }}</span>
                                <span class="truncate text-xs text-gray-700 dark:text-gray-300">{{ $lesson->teacher?->name }} · {{ $lesson->section?->campus?->name }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($lessons['more'] > 0)
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ trans_choice('and :count more lesson|and :count more lessons', $lessons['more'], ['count' => $lessons['more']]) }}</span>
                @endif
            @endif
        </div>

        {{-- Exams --}}
        <div class="{{ $card }}" style="animation-delay: 300ms">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Exams this year') }}</h3>
                <svg viewBox="0 0 40 40" class="art-bob size-8" aria-hidden="true">
                    <rect x="6" y="4" width="22" height="30" rx="2" fill="#e0f2fe" stroke="#0369a1" stroke-width="2" />
                    <path d="M11 12h12M11 18h12M11 24h7" stroke="#0369a1" stroke-width="2" stroke-linecap="round" />
                    <path d="M26 32l8-16 3 1.5-8 16-3.5 1z" fill="#facc15" stroke="#92400e" stroke-width="1.2" />
                </svg>
            </div>
            <ol class="flex flex-col">
                @foreach ($exams as $exam)
                    @php($complete = $exam['students'] > 0 && $exam['withResults'] >= $exam['students'])
                    @php($started = $exam['withResults'] > 0)
                    <li class="flex gap-3">
                        <div class="flex w-4 flex-col items-center">
                            <span @class([
                                'mt-1 size-3.5 rounded-full border-2',
                                'border-emerald-600 bg-emerald-600 dark:border-emerald-400 dark:bg-emerald-400' => $complete,
                                'border-amber-500 bg-amber-200 dark:bg-amber-400/30' => $started && ! $complete,
                                'border-gray-400 bg-white dark:border-gray-500 dark:bg-gray-900' => ! $started,
                            ])></span>
                            @unless ($loop->last)
                                <span class="min-h-5 w-0.5 flex-1 bg-gray-200 dark:bg-white/10"></span>
                            @endunless
                        </div>
                        <div class="flex flex-col pb-3">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $exam['exam']->getLabel() }}</span>
                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                @if ($complete)
                                    {{ __('Marks entered for all :count students', ['count' => $exam['students']]) }}
                                @elseif ($started)
                                    {{ __('Marks entered for :done of :count students', ['done' => $exam['withResults'], 'count' => $exam['students']]) }}
                                @else
                                    {{ __('No marks entered yet') }}
                                @endif
                            </span>
                        </div>
                    </li>
                @endforeach
            </ol>
            @if ($marksUrl)
                <x-filament::link :href="$marksUrl" size="sm" class="mt-auto self-start">{{ __('Enter marks') }}</x-filament::link>
            @endif
        </div>

        {{-- This month's fees --}}
        @if ($showFees)
            <div class="{{ $card }}" style="animation-delay: 400ms">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __(':month fees', ['month' => now()->translatedFormat('F')]) }}</h3>
                    <svg viewBox="0 0 48 40" class="h-8 w-10" aria-hidden="true">
                        <g class="art-bob">
                            <ellipse cx="16" cy="30" rx="12" ry="5" fill="#d97706" /><ellipse cx="16" cy="27" rx="12" ry="5" fill="#fbbf24" />
                            <ellipse cx="16" cy="22" rx="12" ry="5" fill="#d97706" /><ellipse cx="16" cy="19" rx="12" ry="5" fill="#fbbf24" />
                        </g>
                        <g class="art-bob" style="animation-delay: .5s">
                            <circle cx="36" cy="16" r="10" fill="#fbbf24" stroke="#d97706" stroke-width="2.5" />
                            <text x="36" y="20" text-anchor="middle" font-size="10" font-weight="700" fill="#92400e">Rs</text>
                        </g>
                    </svg>
                </div>
                @if ($fees['vouchers'] === 0)
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No fee vouchers for this month yet.') }}</p>
                @else
                    <div class="flex flex-col">
                        <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ \App\Services\FeeService::money($fees['collected']) }}</span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('collected of :billed billed', ['billed' => \App\Services\FeeService::money($fees['billed'])]) }}</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="art-grow h-full rounded-full bg-amber-500" style="width: {{ min(100, $fees['rate']) }}%"></div>
                    </div>
                    <div class="mt-auto flex items-center justify-between gap-2 text-xs">
                        <span class="text-gray-600 dark:text-gray-400">{{ __(':rate% collected', ['rate' => $fees['rate']]) }}</span>
                        <a href="#fees-group" class="font-semibold text-rose-700 hover:underline dark:text-rose-400">{{ trans_choice(':count defaulter|:count defaulters', $fees['defaulters'], ['count' => $fees['defaulters']]) }}</a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
