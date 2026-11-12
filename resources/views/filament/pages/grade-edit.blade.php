<x-filament-panels::page>
    <div class="mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $courseName }}</h2>
        @if($isReadOnly)
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Read-only mode') }}</p>
        @endif
    </div>

    @if($selectedEnrollmentId)
        {{-- Student Detail View --}}
        @php
            $currentIndex = $this->getCurrentStudentIndex();
            $currentStudent = $enrollments[$currentIndex] ?? null;
            $totalStudents = count($enrollments);
        @endphp

        <div class="flex items-center justify-between mb-6 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <x-filament::button
                wire:click="previousStudent"
                size="sm"
                color="gray"
                icon="heroicon-o-chevron-left"
                :disabled="$currentIndex === 0"
            >
                {{ __('Previous') }}
            </x-filament::button>

            <div class="text-center">
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $currentStudent['studentName'] ?? '' }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ ($currentIndex + 1) }} / {{ $totalStudents }}
                </p>
            </div>

            <x-filament::button
                wire:click="nextStudent"
                size="sm"
                color="gray"
                icon-position="after"
                icon="heroicon-o-chevron-right"
                :disabled="$currentIndex === $totalStudents - 1"
            >
                {{ __('Next') }}
            </x-filament::button>
        </div>

        <div class="mb-4">
            <x-filament::button
                wire:click="backToOverview"
                size="sm"
                color="gray"
                icon="heroicon-o-arrow-left"
            >
                {{ __('Back to overview') }}
            </x-filament::button>
        </div>

        @if($currentStudent && count($gradeTypes) > 0)
            {{-- Grades Section --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Grades') }}</x-slot>

                @php $maxTotal = collect($gradeTypes)->sum('total'); $currentCategory = null; @endphp
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400">{{ __('Grade type') }}</th>
                            <th class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">{{ __('Max') }}</th>
                            <th class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">{{ __('Grade') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($gradeTypes as $gt)
                            @if($gt['categoryName'] !== $currentCategory)
                                @php $currentCategory = $gt['categoryName']; @endphp
                                @if($currentCategory)
                                    <tr>
                                        <td colspan="3" class="px-4 py-2 bg-gray-100 dark:bg-gray-900 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">
                                            {{ $currentCategory }}
                                        </td>
                                    </tr>
                                @endif
                            @endif
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $gt['name'] }}</td>
                                <td class="px-4 py-2 text-center text-gray-400">{{ $gt['total'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="{{ $gt['total'] }}"
                                        value="{{ $currentStudent['grades'][$gt['id']] }}"
                                        wire:change="saveGrade({{ $currentStudent['enrollmentId'] }}, {{ $gt['id'] }}, $event.target.value)"
                                        class="w-24 text-center rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                                        @if($isReadOnly) disabled @endif
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                            <td class="px-4 py-2 font-bold text-gray-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-4 py-2 text-center text-gray-400 font-bold">{{ $maxTotal }}</td>
                            <td class="px-4 py-2 text-center text-lg font-bold text-gray-900 dark:text-white">{{ $currentStudent['total'] ?: '—' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </x-filament::section>

            {{-- Result Section --}}
            @if(count($resultTypes) > 0)
                <x-filament::section>
                    <x-slot name="heading">{{ __('Result') }}</x-slot>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($resultTypes as $rt)
                            @php
                                $isActive = $currentStudent['resultTypeId'] == $rt['id'];
                                $color = $rt['color'] ?? '#9ca3af';
                            @endphp
                            <button
                                @if(!$isReadOnly)
                                    wire:click="saveResult({{ $currentStudent['enrollmentId'] }}, '{{ $rt['id'] }}')"
                                @endif
                                class="px-3 py-1.5 text-xs font-medium transition-colors border rounded-md {{ $isReadOnly ? 'opacity-50 cursor-not-allowed' : '' }}"
                                style="{{ $isActive
                                    ? 'background-color: ' . $color . '; color: white; border-color: ' . $color . ';'
                                    : 'background-color: transparent; color: ' . $color . '; border-color: ' . $color . '40;'
                                }}"
                                @if($isReadOnly) disabled @endif
                            >
                                {{ $rt['name'] }}
                            </button>
                        @endforeach

                        @if($currentStudent['resultTypeId'] && !$isReadOnly)
                            <button
                                wire:click="saveResult({{ $currentStudent['enrollmentId'] }}, '')"
                                class="px-2 py-1.5 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                ✕
                            </button>
                        @endif
                    </div>

                    {{-- Comment textarea --}}
                    <div class="mt-3">
                        <textarea
                            rows="2"
                            wire:change="saveComment({{ $currentStudent['enrollmentId'] }}, $event.target.value)"
                            class="w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="{{ __('Comment') }}"
                            @if($isReadOnly) disabled @endif
                        >{{ $comments[$currentStudent['enrollmentId']] ?? '' }}</textarea>
                    </div>
                </x-filament::section>
            @endif
        @endif
    @else
        {{-- Overview --}}
        @if(count($gradeTypes) === 0)
            <x-filament::section>
                <p class="text-sm text-gray-500">{{ __('No evaluation type or grade types configured for this course.') }}</p>
            </x-filament::section>
        @elseif(count($enrollments) === 0)
            <x-filament::section>
                <p class="text-sm text-gray-500">{{ __('No enrollments found for this course.') }}</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Student') }}
                                </th>
                                @foreach($gradeTypes as $gt)
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        @if($gt['categoryName'])
                                            <span class="text-xs text-gray-400 block">({{ $gt['categoryName'] }})</span>
                                        @endif
                                        {{ $gt['name'] }}
                                        <span class="text-xs text-gray-400">/{{ $gt['total'] }}</span>
                                    </th>
                                @endforeach
                                <th class="px-3 py-2 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Total') }}
                                    <span class="text-xs text-gray-400 font-normal">/ {{ collect($gradeTypes)->sum('total') }}</span>
                                </th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Result') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Comment') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($enrollments as $enrollment)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-3 py-2 text-sm whitespace-nowrap">
                                        <button
                                            wire:click="selectStudent({{ $enrollment['enrollmentId'] }})"
                                            class="font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline"
                                        >
                                            {{ $enrollment['studentName'] }}
                                        </button>
                                    </td>
                                    @foreach($gradeTypes as $gt)
                                        <td class="px-3 py-2 text-center text-sm text-gray-900 dark:text-white">
                                            {{ $enrollment['grades'][$gt['id']] !== '' ? $enrollment['grades'][$gt['id']] : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-center text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $enrollment['total'] ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if($enrollment['resultTypeId'])
                                            <span
                                                class="inline-block px-2 py-0.5 text-xs font-medium rounded-full text-white"
                                                style="background-color: {{ $enrollment['resultTypeColor'] ?? '#9ca3af' }};"
                                            >
                                                {{ $enrollment['resultTypeName'] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center text-xs text-gray-500 dark:text-gray-400 max-w-[150px] truncate">
                                        {{ $comments[$enrollment['enrollmentId']] ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
