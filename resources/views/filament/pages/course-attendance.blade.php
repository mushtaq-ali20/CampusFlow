<x-filament-panels::page>
    @if(!$courseId)
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('No course selected.') }}</p>
        </x-filament::section>
    @elseif(count($events) > 0 && count($students) > 0)
        {{-- Mobile: vertical card layout (one card per event, students listed vertically) --}}
        <div class="space-y-4 sm:hidden">
            @foreach($events as $event)
                <x-filament::section>
                    <x-slot name="heading">{{ $event['date'] }}</x-slot>
                    <div class="space-y-2">
                        @foreach($students as $student)
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
                                <span class="text-xs font-medium text-gray-900 dark:text-white">{{ $student['studentName'] }}</span>
                                <div class="flex flex-wrap gap-0.5">
                                    @foreach($attendanceTypes as $type)
                                        @php
                                            $currentTypeId = $student['attendances'][$event['id']] ?? null;
                                            $isActive = $currentTypeId === $type['id'];
                                        @endphp
                                        <x-filament::button
                                            wire:click="toggleAttendance({{ $student['studentId'] }}, {{ $event['id'] }}, {{ $type['id'] }})"
                                            :color="$isActive ? $type['color'] : 'gray'"
                                            :outlined="!$isActive"
                                            size="xs"
                                        >
                                            {{ $type['name'] }}
                                        </x-filament::button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        {{-- Desktop: table layout (events as columns, students as rows) --}}
        <div class="hidden sm:block">
            <x-filament::section>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 sticky left-0 bg-gray-50 dark:bg-gray-700 z-10">{{ __('Student') }}</th>
                                @foreach($events as $event)
                                    <th class="px-2 py-2 text-center whitespace-nowrap">
                                        {{ $event['date'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($students as $student)
                                <tr class="border-b dark:border-gray-600">
                                    <td class="px-3 py-2 sticky left-0 bg-white dark:bg-gray-800 z-10 whitespace-nowrap font-medium">
                                        {{ $student['studentName'] }}
                                    </td>
                                    @foreach($events as $event)
                                        <td class="px-1 py-1 text-center">
                                            <div class="flex flex-col gap-0.5">
                                                @foreach($attendanceTypes as $type)
                                                    @php
                                                        $currentTypeId = $student['attendances'][$event['id']] ?? null;
                                                        $isActive = $currentTypeId === $type['id'];
                                                    @endphp
                                                    <x-filament::button
                                                        wire:click="toggleAttendance({{ $student['studentId'] }}, {{ $event['id'] }}, {{ $type['id'] }})"
                                                        :color="$isActive ? $type['color'] : 'gray'"
                                                        :outlined="!$isActive"
                                                        size="xs"
                                                    >
                                                        {{ $type['name'] }}
                                                    </x-filament::button>
                                                @endforeach
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        {{-- Legend --}}
        <div class="mt-4 flex flex-wrap gap-3 text-sm text-gray-600 dark:text-gray-400">
            @foreach($attendanceTypes as $type)
                <span><x-filament::badge :color="$type['color']">{{ $type['name'] }}</x-filament::badge></span>
            @endforeach
        </div>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('No events or students found for this course.') }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
