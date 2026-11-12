@php
    $card = 'flex flex-col gap-1 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
    $label = 'text-sm text-gray-600 dark:text-gray-400';
    $value = 'text-xl font-semibold text-gray-950 dark:text-white';
@endphp

@if ($sheet)
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('Class average') }}</span>
                <span class="{{ $value }}">{{ $sheet['summary']['average'] !== null ? $sheet['summary']['average'].'%' : '—' }}</span>
            </div>
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('Passed') }}</span>
                <span class="{{ $value }}">{{ $sheet['summary']['passed'] }} / {{ $sheet['summary']['students'] }}</span>
            </div>
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('Failed') }}</span>
                <span class="{{ $value }} {{ $sheet['summary']['failed'] > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">{{ $sheet['summary']['failed'] }}</span>
            </div>
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('Top position') }}</span>
                <span class="{{ $value }} truncate">{{ $sheet['summary']['top'] ?? '—' }}</span>
            </div>
            <div class="{{ $card }}">
                <span class="{{ $label }}">{{ __('Weakest subject') }}</span>
                <span class="{{ $value }} truncate">{{ $sheet['summary']['weakest'] ?? '—' }}</span>
            </div>
        </div>

        @if (count($sheet['analysis']) > 0)
            <x-filament::section :heading="__('Subject analysis')" collapsible collapsed>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-600 dark:text-gray-400">
                                <th class="py-2 pe-4 font-medium">{{ __('Subject') }}</th>
                                <th class="py-2 pe-4 text-end font-medium">{{ __('Average') }}</th>
                                <th class="py-2 pe-4 text-end font-medium">{{ __('Passed') }}</th>
                                <th class="py-2 pe-4 text-end font-medium">{{ __('Failed') }}</th>
                                <th class="py-2 text-end font-medium">{{ __('Highest marks') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($sheet['analysis'] as $subject)
                                <tr>
                                    <td class="py-2 pe-4 text-gray-950 dark:text-white">{{ $subject['name'] }}</td>
                                    <td class="py-2 pe-4 text-end tabular-nums">{{ $subject['average'] !== null ? $subject['average'].'%' : '—' }}</td>
                                    <td class="py-2 pe-4 text-end tabular-nums">{{ $subject['passed'] }}</td>
                                    <td class="py-2 pe-4 text-end tabular-nums {{ $subject['failed'] > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">{{ $subject['failed'] }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $subject['highest'] !== null ? rtrim(rtrim(number_format($subject['highest'], 2), '0'), '.') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($sheet['weight'] > 0)
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Each subject counts the exam for :exam% and the class assessments of the term for :weight%.', ['exam' => 100 - $sheet['weight'], 'weight' => $sheet['weight']]) }}</p>
        @endif
    </div>
@endif
