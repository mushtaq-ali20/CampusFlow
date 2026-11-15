{{--
    The student's profile card: initials, name, admission status and one line with the admission number, class,
    branch and year.
--}}
@php
    $admission = $student->latestAdmission;
    $initials = collect(preg_split('/\s+/', trim($student->name)))->filter()->take(2)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $line = $admission
        ? collect([$admission->admission_number, $admission->section?->full_name, $admission->campus?->name, $admission->year?->name])->filter()->implode(' · ')
        : __('Not admitted');
@endphp

<div class="flex flex-wrap items-center gap-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-emerald-100 text-[22px] font-bold text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200">{{ $initials }}</span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2.5">
            <h2 class="m-0 text-2xl font-bold text-gray-900 dark:text-white">{{ $student->name }}</h2>
            @if ($admission?->status)
                <x-filament::badge :color="$admission->status->getColor()">{{ $admission->status->getLabel() }}</x-filament::badge>
            @endif
        </div>
        <p class="mt-1 text-[13.5px] text-gray-600 dark:text-gray-400">{{ $line }}</p>
    </div>
</div>
