<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Timetable — monthly') }}</x-slot>
        <x-slot name="description">{{ __('Each date shows the lessons of its weekday. Click a lesson to update or reconfigure the timetable.') }}</x-slot>

        <x-slot name="afterHeader">
            <div class="flex items-center gap-2">
                <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="previousMonth" :label="__('Previous month')" />
                <span class="min-w-32 text-center text-sm font-medium">{{ $this->monthLabel() }}</span>
                <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="nextMonth" :label="__('Next month')" />
                <x-filament::button size="xs" color="gray" wire:click="currentMonth">{{ __('Today') }}</x-filament::button>
            </div>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[56rem] table-fixed border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        @foreach ([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')] as $day)
                            <th class="border border-gray-200 p-2 text-left font-medium dark:border-gray-700">{{ $day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->weeks() as $week)
                        <tr>
                            @foreach ($week as $cell)
                                <td @class([
                                    'h-28 border border-gray-200 p-1 align-top dark:border-gray-700',
                                    'bg-gray-50 text-gray-400 dark:bg-gray-900 dark:text-gray-500' => ! $cell['inMonth'],
                                    'bg-amber-50 dark:bg-amber-500/10' => $cell['isToday'],
                                ])>
                                    <div @class(['mb-1 font-semibold', 'text-amber-600 dark:text-amber-400' => $cell['isToday']])>{{ $cell['date']->day }}</div>

                                    @foreach ($cell['lessons'] as $lesson)
                                        @php($colours = $this->subjectColours($lesson->subject_id))
                                        <button
                                            type="button"
                                            wire:click="mountAction('reconfigure', { entry: {{ $lesson->id }} })"
                                            title="{{ $lesson->period->name }} — {{ $lesson->subject->name }} — {{ $lesson->section->full_name }} — {{ $lesson->teacher->name }}"
                                            class="mb-0.5 block w-full truncate rounded px-1 py-0.5 text-left text-gray-800 ring-1 dark:text-gray-100 {{ $colours['cell'] }}"
                                        >
                                            <span class="font-semibold {{ $colours['subject'] }}">{{ $lesson->subject->name }}</span> · {{ $lesson->period->name }} · {{ $lesson->section->full_name }}
                                        </button>
                                    @endforeach

                                    @if ($cell['more'] > 0)
                                        <div class="text-gray-500 dark:text-gray-400">+ {{ $cell['more'] }} {{ __('more') }}</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
