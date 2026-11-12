<x-filament-panels::page>
    <div class="mb-4">
        <label for="period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Period') }}</label>
        <select wire:model.live="selectedPeriodId" id="period" class="block w-full max-w-xs rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            @foreach(\App\Models\Period::all() as $period)
                <option value="{{ $period->id }}">{{ $period->name }}</option>
            @endforeach
        </select>
    </div>

    @if(count($coursesData) > 0)
        <x-filament::section class="mb-6">
            <x-slot name="heading">{{ __('Attendance Distribution by Course') }}</x-slot>
            <div
                wire:ignore
                x-data="{ chart: null }"
                x-init="
                    const loadChartJs = () => new Promise(resolve => {
                        if (window.Chart) { resolve(); return; }
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
                        s.onload = resolve;
                        document.head.appendChild(s);
                    });

                    const render = (data) => {
                        if (chart) { chart.destroy(); }
                        const canvas = $el.querySelector('canvas');
                        if (!canvas || !data.labels) return;
                        chart = new Chart(canvas, {
                            type: 'bar',
                            data: data,
                            options: {
                                responsive: true,
                                scales: {
                                    x: { stacked: true },
                                    y: { stacked: true, beginAtZero: true },
                                },
                            }
                        });
                    };

                    loadChartJs().then(() => render($wire.chartData));

                    $wire.$watch('chartData', (newData) => render(newData));
                "
            >
                <canvas height="120"></canvas>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Detailed Data') }}</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2">{{ __('Course') }}</th>
                            @foreach($attendanceTypes as $type)
                                <th class="px-4 py-2 text-right">{{ $type['name'] }}</th>
                            @endforeach
                            <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coursesData as $row)
                            <tr class="border-b dark:border-gray-600">
                                <td class="px-4 py-2 font-medium">{{ $row['name'] }}</td>
                                @foreach($attendanceTypes as $type)
                                    <td class="px-4 py-2 text-right">{{ $row['type_'.$type['id']] ?? 0 }}</td>
                                @endforeach
                                <td class="px-4 py-2 text-right font-semibold">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">{{ __('No attendance data found for this period.') }}</p>
        </x-filament::section>
    @endif


</x-filament-panels::page>
