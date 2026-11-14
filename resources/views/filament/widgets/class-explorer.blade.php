@php
    $statusDot = [
        'present' => 'bg-emerald-500',
        'late' => 'bg-amber-500',
        'absent' => 'bg-rose-600',
        'leave' => 'bg-sky-500',
    ];
@endphp

<x-filament-widgets::widget>
    <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 dark:border-white/5">
        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Classes') }}">
            @foreach ($classes as $schoolClass)
                @php($active = $schoolClass->is($class))
                <button
                    type="button"
                    wire:click="selectClass({{ $schoolClass->id }})"
                    aria-pressed="{{ $active ? 'true' : 'false' }}"
                    @class([
                        'cf-press h-9 rounded-full border px-3.5 text-sm font-semibold whitespace-nowrap',
                        'border-(--primary-600) bg-(--primary-600) text-white dark:border-(--primary-500) dark:bg-(--primary-500)' => $active,
                        'border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50 dark:border-white/15 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10' => ! $active,
                    ])
                >{{ $schoolClass->name }}</button>
            @endforeach
        </div>

        @if ($class && $snapshot)
            <div wire:key="class-{{ $class->id }}" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <span class="text-lg font-bold text-gray-950 dark:text-white">{{ $class->name }}</span>
                    <span><b class="text-gray-950 dark:text-white">{{ $snapshot['students'] }}</b> {{ __('students') }}</span>
                    <span><b class="text-gray-950 dark:text-white">{{ count($snapshot['sections']) }}</b> {{ __('sections') }}</span>
                    <span>
                        <b class="text-emerald-700 dark:text-emerald-400">{{ $snapshot['marked'] > 0 ? $snapshot['present'].' / '.$snapshot['marked'] : '—' }}</b>
                        {{ __('attended') }}
                    </span>
                    <span>{{ trans_choice(':count subject|:count subjects', $snapshot['subjects'], ['count' => $snapshot['subjects']]) }}</span>
                    <span>
                        {{ __('Monthly fee') }}
                        <b class="text-gray-950 dark:text-white">
                            @if ($snapshot['monthlyFee'] === null)
                                —
                            @elseif ($snapshot['monthlyFee']['min'] === $snapshot['monthlyFee']['max'])
                                {{ \App\Services\FeeService::money($snapshot['monthlyFee']['min']) }}
                            @else
                                {{ \App\Services\FeeService::money($snapshot['monthlyFee']['min']) }} – {{ \App\Services\FeeService::money($snapshot['monthlyFee']['max']) }}
                            @endif
                        </b>
                    </span>
                </div>

                <div class="cf-sections">
                    @forelse ($snapshot['sections'] as $entry)
                        @php($section = $entry['section'])
                        <div class="cf-rise flex min-w-0 flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-white/10" style="animation-delay: {{ $loop->index * 80 }}ms">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Section :name', ['name' => $section->name]) }}</span>
                                @if ($canOpenSections)
                                    <a href="{{ \App\Filament\Resources\Sections\SectionResource::getUrl('view', ['record' => $section]) }}" class="cf-link text-sm">{{ __('Open section') }}</a>
                                @endif
                            </div>
                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $section->campus?->name }}
                                @if ($section->classTeacher)
                                    · {{ __('Class teacher :name', ['name' => $section->classTeacher->name]) }}
                                @endif
                                @if ($entry['date'])
                                    · {{ __('Attendance of :date', ['date' => $entry['date']->isToday() ? __('today') : $entry['date']->translatedFormat('j M')]) }}
                                @endif
                            </span>

                            @if ($entry['students']->isEmpty())
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No students admitted yet.') }}</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($entry['students']->take($perSection) as $student)
                                        @php($admission = $student['admission'])
                                        @php($status = $student['status'])
                                        @php($name = $admission->student?->name ?? '')
                                        @php($initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn (string $part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode(''))
                                        <a
                                            @if ($canOpenAdmissions) href="{{ \App\Filament\Resources\Admissions\AdmissionResource::getUrl('edit', ['record' => $admission]) }}" @endif
                                            title="{{ $name }} — {{ $admission->admission_number }} — {{ $status?->getLabel() ?? __('Not marked') }}"
                                            class="cf-chip"
                                        >
                                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $initials }}</span>
                                            <span class="truncate">{{ $name }}</span>
                                            <span class="size-2 shrink-0 rounded-full {{ $status ? $statusDot[$status->value] : 'bg-gray-300 dark:bg-gray-600' }}" role="img" aria-label="{{ $status?->getLabel() ?? __('Not marked') }}"></span>
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

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                    @foreach (\App\Enums\ClassAttendanceStatus::cases() as $status)
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full {{ $statusDot[$status->value] }}"></span>{{ $status->getLabel() }}</span>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>{{ __('Not marked') }}</span>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
