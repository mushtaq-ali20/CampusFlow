<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Timetable — weekly') }}</x-slot>
        <x-slot name="description">
            @if ($teacher)
                {{ __('Lessons of :teacher. Click a lesson to update or reconfigure the timetable.', ['teacher' => $teacher->name]) }}
            @elseif ($section)
                {{ __('Lessons of :section, :branch. Click a lesson to update or reconfigure the timetable.', ['section' => $section->full_name, 'branch' => $section->campus?->name]) }}
            @else
                {{ __('No lessons for these filters.') }}
            @endif
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex flex-wrap items-center gap-2">
                @if ($canPickSection)
                    <label class="sr-only" for="timetable-weekly-section">{{ __('Section') }}</label>
                    <x-filament::input.wrapper class="min-w-56">
                        <x-filament::input.select id="timetable-weekly-section" wire:model.live="sectionId">
                            @foreach ($sectionOptions as $id => $label)
                                <option value="{{ $id }}" @selected($section?->id === $id)>{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif
                @if ($manageUrl && ($teacher || $section))
                    <x-filament::button tag="a" :href="$manageUrl" size="sm" color="gray" :icon="$hasLessons ? 'heroicon-m-pencil-square' : 'heroicon-m-plus'">
                        {{ $hasLessons ? __('Manage timetable') : __('Add timetable') }}
                    </x-filament::button>
                @endif
                @if ($manageUrl && ! $teacher)
                    <x-filament::dropdown placement="bottom-end">
                        <x-slot name="trigger">
                            <x-filament::button size="sm" color="gray" icon="heroicon-m-plus">{{ __('Add timetable for a section') }}</x-filament::button>
                        </x-slot>
                        <x-filament::dropdown.list>
                            @foreach ($addUrls as $shiftValue => $addUrl)
                                <x-filament::dropdown.list.item tag="a" :href="$addUrl">
                                    {{ \App\Enums\Shift::from($shiftValue)->getLabel() }}
                                </x-filament::dropdown.list.item>
                            @endforeach
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                @endif
            </div>
        </x-slot>

        @if ($teacher || $section)
            <div class="overflow-x-auto">
                <div class="grid min-w-[56rem] grid-cols-[7rem_repeat(6,minmax(7.5rem,1fr))] gap-1.5 text-xs">
                    <span></span>
                    @foreach ($days as $day)
                        <span class="px-1.5 py-1 font-semibold text-gray-600 dark:text-gray-400">{{ __(\App\Models\TimetableEntry::DAYS[$day]) }}</span>
                    @endforeach

                    @foreach ($periodsByShift as $shiftPeriods)
                    <div class="col-span-7 mt-2 flex items-baseline gap-2 border-b border-gray-200 px-1.5 pb-1 dark:border-white/10">
                        <span class="text-sm font-semibold text-sky-700 dark:text-sky-300">{{ __(':shift shift', ['shift' => $shiftPeriods->first()->shift->getLabel()]) }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ substr((string) $shiftPeriods->min('start_time'), 0, 5) }}–{{ substr((string) $shiftPeriods->max('end_time'), 0, 5) }}</span>
                    </div>
                    @foreach ($shiftPeriods as $period)
                        <div class="flex flex-col justify-center px-1.5 py-1">
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $period->name }}</span>
                            <span class="text-gray-600 dark:text-gray-400">{{ str_replace(' - ', '–', $period->timeRange()) }}</span>
                        </div>

                        @if ($period->is_break)
                            <div class="col-span-6 flex items-center justify-center rounded-lg bg-gray-100 py-1.5 font-medium tracking-wide text-gray-600 uppercase dark:bg-white/5 dark:text-gray-400">
                                {{ $period->name }}
                            </div>
                        @else
                            @foreach ($days as $day)
                                @php($lessons = $lessonsBySlot->get($period->id.'-'.$day, collect()))
                                @if ($lessons->isEmpty())
                                    @if ($manageUrl)
                                        <a href="{{ $manageUrl }}" class="flex min-h-12 items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-500 hover:border-amber-400 hover:text-amber-700 dark:border-white/15 dark:text-gray-400 dark:hover:text-amber-300">{{ __('Free') }}</a>
                                    @else
                                        <span class="flex min-h-12 items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-500 dark:border-white/15 dark:text-gray-400">{{ __('Free') }}</span>
                                    @endif
                                @else
                                    <div class="flex flex-col gap-1">
                                        @foreach ($lessons as $lesson)
                                            @php($colours = $this->subjectColours($lesson->subject_id))
                                            <button
                                                type="button"
                                                wire:click="mountAction('reconfigure', { entry: {{ $lesson->id }} })"
                                                title="{{ $lesson->subject?->name }} — {{ $lesson->section?->full_name }} — {{ $lesson->teacher?->name }}"
                                                class="flex min-h-12 flex-col justify-center gap-0.5 rounded-lg px-2 py-1.5 text-left ring-1 transition {{ $colours['cell'] }}"
                                            >
                                                <span class="truncate font-semibold {{ $colours['subject'] }}">{{ $lesson->subject?->name }}</span>
                                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $teacher ? $lesson->section?->full_name : $lesson->teacher?->name }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                    @endforeach
                </div>
            </div>

            @if ($manageUrl && count($missingShifts) > 0)
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ($missingShifts as $missingShift)
                        <span>{{ __('No :shift lessons.', ['shift' => strtolower($missingShift->getLabel())]) }}</span>
                        <x-filament::button tag="a" :href="$addUrls[$missingShift->value]" size="sm" color="gray" icon="heroicon-m-plus">
                            {{ __('Add :shift timetable', ['shift' => strtolower($missingShift->getLabel())]) }}
                        </x-filament::button>
                    @endforeach
                </div>
            @endif
        @elseif ($manageUrl)
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <span>{{ __('No timetable yet.') }}</span>
                @foreach ($addUrls as $shiftValue => $addUrl)
                    <x-filament::button tag="a" :href="$addUrl" size="sm" color="gray" icon="heroicon-m-plus">
                        {{ __('Add :shift timetable', ['shift' => strtolower(\App\Enums\Shift::from($shiftValue)->getLabel())]) }}
                    </x-filament::button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
