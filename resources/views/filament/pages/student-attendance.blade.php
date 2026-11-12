<x-filament-panels::page>
    {{-- Course selector --}}
    <div class="mb-4">
        <label for="courseSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Course') }}</label>
        <select wire:model.live="courseId" id="courseSelect" class="block w-full max-w-md rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            @foreach($enrollments as $enrollment)
                <option value="{{ $enrollment['courseId'] }}">{{ $enrollment['label'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $attendanceRatio !== null ? $attendanceRatio.'%' : '—' }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase font-semibold">{{ __('Attendance Ratio') }}</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $absenceCount }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase font-semibold">{{ __('Number of Absences') }}</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Events table --}}
    <x-filament::section>
        <x-slot name="heading">{{ $studentName }}</x-slot>

        @if(count($events) > 0)
            <div class="space-y-3">

                @foreach($events as $event)
                    <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $event['name'] }}</span>
                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $event['date'] }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($attendanceTypes as $type)
                                @php
                                    $isActive = $event['currentTypeId'] === $type['id'];
                                @endphp
                                <x-filament::button
                                    wire:click="toggleAttendance({{ $event['id'] }}, {{ $type['id'] }})"
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
        @else
            <p class="text-sm text-gray-500">{{ __('No events found for this course.') }}</p>
        @endif
    </x-filament::section>

    {{-- Legend --}}
    <div class="mt-4 flex flex-wrap gap-3 text-sm text-gray-600 dark:text-gray-400">
        @foreach($attendanceTypes as $type)
            <span><x-filament::badge :color="$type['color']">{{ $type['name'] }}</x-filament::badge></span>
        @endforeach
    </div>
</x-filament-panels::page>
