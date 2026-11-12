<x-filament-panels::page>
    <x-filament::section class="mb-6">
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
                    if (!canvas || !data.labels || !data.labels.length) return;
                    chart = new Chart(canvas, {
                        type: 'line',
                        data: data,
                        options: {
                            responsive: true,
                            aspectRatio: 4,
                            interaction: { intersect: false, mode: 'index' },
                            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
                        }
                    });
                };

                loadChartJs().then(() => render($wire.chartData));
                $wire.$watch('chartData', (newData) => render(newData));
            "
        >
            <canvas></canvas>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div class="flex items-center justify-end gap-2 mb-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Start from period:') }}</span>
            <select wire:model.live="startFromPeriodId" class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                @foreach($allPeriods as $period)
                    <option value="{{ $period['id'] }}">{{ $period['name'] }}</option>
                @endforeach
            </select>
        </div>

        @if(count($reportData) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2">{{ __('Period') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Male') }} %</th>
                            <th class="px-4 py-2 text-right">{{ __('Female') }} %</th>
                            <th class="px-4 py-2 text-right">{{ __('Unknown') }} %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData as $row)
                            <tr class="border-b dark:border-gray-600 {{ $row['isYearSummary'] ? 'font-bold bg-gray-50 dark:bg-gray-800' : '' }}">
                                <td class="px-4 py-2">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-right">{{ $row['male'] }}%</td>
                                <td class="px-4 py-2 text-right">{{ $row['female'] }}%</td>
                                <td class="px-4 py-2 text-right">{{ $row['unknown'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __('No data available.') }}</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
