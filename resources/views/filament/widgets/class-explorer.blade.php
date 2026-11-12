@php
    $statusDot = [
        'present' => '#16a34a',
        'late' => '#f59e0b',
        'absent' => '#e11d48',
        'leave' => '#0284c7',
    ];
    $sectionTones = [
        ['badge' => 'bg-sky-50 text-sky-800 dark:bg-sky-400/10 dark:text-sky-300', 'ground' => '#e0f2fe', 'uniform' => '#0369a1'],
        ['badge' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300', 'ground' => '#dcfce7', 'uniform' => '#15803d'],
        ['badge' => 'bg-violet-50 text-violet-800 dark:bg-violet-400/10 dark:text-violet-300', 'ground' => '#ede9fe', 'uniform' => '#6d28d9'],
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section :heading="__('Classes')" :description="__('Choose a class to see its students and today\'s attendance.')" icon="heroicon-o-academic-cap">
        <div class="flex flex-col gap-5">
            <div class="overflow-x-auto pb-2">
                <div class="flex min-w-max items-end gap-2 p-1" role="group" aria-label="{{ __('Classes') }}">
                    @foreach ($classes as $schoolClass)
                        @php($active = $schoolClass->is($class))
                        <button
                            type="button"
                            wire:click="selectClass({{ $schoolClass->id }})"
                            aria-pressed="{{ $active ? 'true' : 'false' }}"
                            @class([
                                'flex min-h-11 flex-col items-center gap-1 rounded-lg border px-3 py-2 text-sm font-semibold whitespace-nowrap transition hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-amber-500',
                                'border-amber-500 bg-amber-500 text-gray-950' => $active,
                                'border-gray-200 bg-white text-gray-800 hover:border-amber-400 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200' => ! $active,
                            ])
                        >
                            <span @class(['w-2 rounded-sm', 'bg-gray-950' => $active, 'bg-amber-400' => ! $active]) style="height: {{ 6 + $loop->index * 2 }}px"></span>
                            {{ $schoolClass->name }}
                        </button>
                    @endforeach
                    <svg viewBox="0 0 40 40" class="art-bob size-10 shrink-0 text-gray-900 dark:text-gray-100" role="img" aria-label="{{ __('Graduation') }}">
                        <polygon points="20,8 38,16 20,24 2,16" fill="currentColor" />
                        <path d="M10 20v7c0 3 20 3 20 0v-7" fill="currentColor" />
                        <path d="M34 17v10" stroke="#f59e0b" stroke-width="2" />
                        <circle cx="34" cy="29" r="2.5" fill="#f59e0b" />
                    </svg>
                </div>
            </div>

            @if ($class && $snapshot)
                <div wire:key="class-{{ $class->id }}" class="flex flex-wrap gap-5">
                    <div class="flex min-w-0 flex-[1_1_15rem] flex-col gap-3">
                        <svg viewBox="0 0 260 150" class="art-rise h-auto w-full rounded-xl bg-amber-50 dark:bg-amber-400/10" role="img" aria-label="{{ __('Classroom of :class', ['class' => $class->name]) }}">
                            <rect x="40" y="18" width="180" height="76" rx="4" fill="#1f5a33" stroke="#7c4a1e" stroke-width="5" />
                            <text x="130" y="54" text-anchor="middle" font-size="20" font-weight="700" fill="#fafaf9">{{ $class->name }}</text>
                            <text x="130" y="76" text-anchor="middle" font-size="11" fill="#d1fae5">{{ trans_choice(':count subject|:count subjects', $snapshot['subjects'], ['count' => $snapshot['subjects']]) }}</text>
                            <rect x="160" y="88" width="22" height="4" rx="1" fill="#fafaf9" />
                            <g fill="#b45309"><rect x="30" y="118" width="56" height="8" rx="2" /><rect x="102" y="118" width="56" height="8" rx="2" /><rect x="174" y="118" width="56" height="8" rx="2" /></g>
                            <path d="M36 126v18M80 126v18M108 126v18M152 126v18M180 126v18M224 126v18" stroke="#78350f" stroke-width="3" />
                            <g class="art-bob"><circle cx="58" cy="108" r="7" fill="#e0ac69" /><path d="M51 107a7 7 0 0 1 14 0c-3-2.5-11-2.5-14 0z" fill="#2d1b0e" /></g>
                            <g class="art-bob" style="animation-delay: .4s"><circle cx="130" cy="108" r="7" fill="#c68642" /><path d="M123 110c-1-10 15-10 14 0v6c-2-2-2-4-2-6-3-3-7-3-10 0 0 2 0 4-2 6z" fill="#1f1f1f" /></g>
                            <g class="art-bob" style="animation-delay: .8s"><circle cx="202" cy="108" r="7" fill="#f1c27d" /><path d="M195 107a7 7 0 0 1 14 0c-3-2.5-11-2.5-14 0z" fill="#5a3825" /></g>
                        </svg>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-lg border border-gray-200 p-2.5 dark:border-white/10">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('Students') }}</dt>
                                <dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ $snapshot['students'] }}</dd>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2.5 dark:border-white/10">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('Sections') }}</dt>
                                <dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ count($snapshot['sections']) }}</dd>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2.5 dark:border-white/10">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('Attended') }}</dt>
                                <dd class="text-lg font-semibold text-emerald-700 dark:text-emerald-400">{{ $snapshot['marked'] > 0 ? $snapshot['present'].' / '.$snapshot['marked'] : '—' }}</dd>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2.5 dark:border-white/10">
                                <dt class="text-xs text-gray-600 dark:text-gray-400">{{ __('Monthly fee') }}</dt>
                                <dd class="text-lg font-semibold text-gray-950 dark:text-white">
                                    @if ($snapshot['monthlyFee'] === null)
                                        —
                                    @elseif ($snapshot['monthlyFee']['min'] === $snapshot['monthlyFee']['max'])
                                        {{ \App\Services\FeeService::money($snapshot['monthlyFee']['min']) }}
                                    @else
                                        <span class="block text-sm leading-6 whitespace-nowrap">{{ \App\Services\FeeService::money($snapshot['monthlyFee']['min']) }} – {{ \App\Services\FeeService::money($snapshot['monthlyFee']['max']) }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                            @foreach (\App\Enums\ClassAttendanceStatus::cases() as $status)
                                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background: {{ $statusDot[$status->value] }}"></span>{{ $status->getLabel() }}</span>
                            @endforeach
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>{{ __('Not marked') }}</span>
                        </div>
                    </div>

                    <div class="grid min-w-0 flex-[2_1_22rem] grid-cols-1 content-start gap-5 xl:grid-cols-2">
                        @forelse ($snapshot['sections'] as $entry)
                            @php($section = $entry['section'])
                            @php($tone = $sectionTones[$loop->index % count($sectionTones)])
                            <div class="flex flex-col gap-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $tone['badge'] }}">{{ __('Section :name', ['name' => $section->name]) }}</span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ $section->campus?->name }}
                                            @if ($section->classTeacher)
                                                · {{ __('Class teacher :name', ['name' => $section->classTeacher->name]) }}
                                            @endif
                                            @if ($entry['date'])
                                                · {{ __('Attendance of :date', ['date' => $entry['date']->isToday() ? __('today') : $entry['date']->translatedFormat('j M')]) }}
                                            @endif
                                        </span>
                                    </div>
                                    @if ($canOpenSections)
                                        <x-filament::link :href="\App\Filament\Resources\Sections\SectionResource::getUrl('view', ['record' => $section])" size="sm">{{ __('Open section') }}</x-filament::link>
                                    @endif
                                </div>

                                @if ($entry['students']->isEmpty())
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No students admitted yet.') }}</p>
                                @else
                                    <div class="grid grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-2.5">
                                        @foreach ($entry['students']->take($perSection) as $student)
                                            @php($admission = $student['admission'])
                                            @php($look = \App\Filament\Widgets\ClassExplorer::avatar($admission->id, $admission->student?->gender_id))
                                            @php($status = $student['status'])
                                            <a
                                                @if ($canOpenAdmissions) href="{{ \App\Filament\Resources\Admissions\AdmissionResource::getUrl('edit', ['record' => $admission]) }}" @endif
                                                title="{{ $admission->student?->name }} — {{ $admission->admission_number }} — {{ $status?->getLabel() ?? __('Not marked') }}"
                                                class="art-lift art-pop flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 px-1.5 py-2.5 text-center dark:border-white/10 dark:bg-white/5"
                                                style="animation-delay: {{ $loop->index * 40 }}ms"
                                            >
                                                <svg viewBox="0 0 48 48" class="size-12" aria-hidden="true">
                                                    <circle cx="24" cy="24" r="24" fill="{{ $tone['ground'] }}" />
                                                    @if ($look['girl'])
                                                        <path d="M14 23c-1-13 21-13 20 0v12c-3-3-3-9-3-12-4-5-10-5-14 0 0 3 0 9-3 12z" fill="{{ $look['hair'] }}" />
                                                    @endif
                                                    <path d="M8 48c0-10 7-15 16-15s16 5 16 15z" fill="{{ $tone['uniform'] }}" />
                                                    <path d="M20 33l4 5 4-5" fill="#fafaf9" />
                                                    <rect x="21" y="27" width="6" height="7" fill="{{ $look['skin'] }}" />
                                                    <circle cx="24" cy="21" r="9" fill="{{ $look['skin'] }}" />
                                                    @unless ($look['girl'])
                                                        <path d="M15 20c0-10 18-10 18 0-3-4-15-4-18 0z" fill="{{ $look['hair'] }}" />
                                                    @endunless
                                                    <circle cx="20.5" cy="21" r="1.2" fill="#1c1917" />
                                                    <circle cx="27.5" cy="21" r="1.2" fill="#1c1917" />
                                                    <path d="M21 25q3 2.4 6 0" stroke="#1c1917" stroke-width="1.3" fill="none" stroke-linecap="round" />
                                                    <circle cx="40" cy="40" r="5.5" fill="{{ $status ? $statusDot[$status->value] : '#a8a29e' }}" stroke="#ffffff" stroke-width="2" />
                                                </svg>
                                                <span class="line-clamp-2 text-xs leading-tight font-semibold text-gray-900 dark:text-gray-100">{{ $admission->student?->name }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                    @if ($entry['students']->count() > $perSection)
                                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ trans_choice('and :count more student|and :count more students', $entry['students']->count() - $perSection, ['count' => $entry['students']->count() - $perSection]) }}</span>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('This class has no sections this academic year.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
