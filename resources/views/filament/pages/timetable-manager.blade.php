<x-filament-panels::page>
    @php
        $periodsByShift = $this->periodsByShift();
        $missingShifts = $this->missingShifts();
        $entries = $this->entries();
        $days = $this->days();
        $section = $this->selectedSection();
        $teachers = $this->teacherOptions();
        $manage = $this->canManage();
    @endphp

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button
            :color="$mode === 'section' ? 'primary' : 'gray'"
            wire:click="$set('mode', 'section')"
            size="sm"
        >
            {{ __('By class and section') }}
        </x-filament::button>
        <x-filament::button
            :color="$mode === 'teacher' ? 'primary' : 'gray'"
            wire:click="$set('mode', 'teacher')"
            size="sm"
        >
            {{ __('By teacher') }}
        </x-filament::button>
    </div>

    @if ($mode === 'section')
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Branch') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="campusId" :disabled="auth()->user()->campus_id !== null">
                        <option value="">{{ __('All branches') }}</option>
                        @foreach ($this->campusOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Shift') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="shift">
                        <option value="">{{ __('All shifts') }}</option>
                        @foreach ($this->shiftOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Class') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="classId">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($this->classOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Section') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sectionId">
                        <option value="">{{ __('Choose a section') }}</option>
                        @foreach ($this->sectionOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
    @else
        <label class="block md:w-1/3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Teacher') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="teacherId">
                    <option value="">{{ __('Choose a teacher') }}</option>
                    @foreach ($teachers as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    @endif

    @if (($mode === 'section' && $section) || ($mode === 'teacher' && filled($teacherId)))
        <x-filament::section>
            <x-slot name="heading">
                @if ($mode === 'section')
                    {{ $section->campus->name }} — {{ $section->full_name }}
                @else
                    {{ $teachers[(int) $teacherId] ?? '' }}
                @endif
            </x-slot>
            <x-slot name="description">
                @if ($mode === 'section' && $manage)
                    {{ __('Click a period to set or change its subject and teacher.') }}
                @elseif ($mode === 'teacher')
                    {{ __('Click a lesson to open and change the timetable of its section.') }}
                @else
                    {{ __('Only administrators can change the timetable.') }}
                @endif
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="border border-gray-200 p-2 text-left dark:border-gray-700">{{ __('Period') }}</th>
                            @foreach ($days as $day)
                                <th class="border border-gray-200 p-2 text-left dark:border-gray-700">{{ $day }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($periodsByShift as $shiftPeriods)
                            <tr>
                                <th colspan="{{ count($days) + 1 }}" class="border border-gray-200 bg-sky-50 p-2 text-left font-semibold text-sky-800 dark:border-gray-700 dark:bg-sky-500/10 dark:text-sky-300">
                                    {{ __(':shift shift', ['shift' => $shiftPeriods->first()->shift->getLabel()]) }}
                                    <span class="font-normal text-gray-500 dark:text-gray-400">· {{ substr((string) $shiftPeriods->min('start_time'), 0, 5) }} – {{ substr((string) $shiftPeriods->max('end_time'), 0, 5) }}</span>
                                </th>
                            </tr>
                        @foreach ($shiftPeriods as $period)
                            <tr @class(['bg-gray-50 dark:bg-gray-900' => $period->is_break])>
                                <td class="border border-gray-200 p-2 align-top dark:border-gray-700">
                                    <div class="font-medium">{{ $period->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $period->timeRange() }}</div>
                                </td>
                                @if ($period->is_break)
                                    <td colspan="{{ count($days) }}" class="border border-gray-200 p-2 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ __('Break') }}</td>
                                @else
                                    @foreach ($days as $dayNumber => $day)
                                        @php($entry = $entries->get($period->id.'-'.$dayNumber))
                                        <td class="border border-gray-200 p-0 align-top dark:border-gray-700">
                                            @if ($mode === 'section' && $manage)
                                                <button type="button" wire:click="mountAction('editCell', { period: {{ $period->id }}, day: {{ $dayNumber }} })" class="block h-full w-full p-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                                    @if ($entry)
                                                        <div class="font-medium">{{ $entry->subject->name }}</div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->teacher->name }}</div>
                                                    @else
                                                        <span class="text-xs text-gray-400 dark:text-gray-500">+ {{ __('Add') }}</span>
                                                    @endif
                                                </button>
                                            @elseif ($mode === 'teacher' && $entry)
                                                <button type="button" wire:click="openSection({{ $entry->section_id }})" class="block h-full w-full p-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                                    <div class="font-medium">{{ $entry->subject->name }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->section->full_name }}</div>
                                                </button>
                                            @else
                                                <div class="p-2">
                                                    @if ($entry)
                                                        <div class="font-medium">{{ $entry->subject->name }}</div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $mode === 'section' ? $entry->teacher->name : $entry->section->full_name }}</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                        @empty
                            <tr>
                                <td colspan="{{ count($days) + 1 }}" class="border border-gray-200 p-4 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    @if ($mode === 'teacher')
                                        {{ __('This teacher has no lessons yet.') }}
                                    @else
                                        {{ __('No periods are configured for this shift yet. Add them in Timetable periods.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($manage && count($missingShifts) > 0)
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ($missingShifts as $missingShift)
                        <span>{{ __('No :shift lessons.', ['shift' => strtolower($missingShift->getLabel())]) }}</span>
                        <x-filament::button size="sm" color="gray" icon="heroicon-m-plus" wire:click="addTimetableForShift('{{ $missingShift->value }}')">
                            {{ __('Add :shift timetable', ['shift' => strtolower($missingShift->getLabel())]) }}
                        </x-filament::button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $mode === 'section' ? __('Choose a branch, class and section to see and manage its timetable.') : __('Choose a teacher to see the timetable of the teacher.') }}
        </p>
    @endif
</x-filament-panels::page>
