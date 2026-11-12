<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span>{{ Carbon\Carbon::parse($this->selectedDate)->translatedFormat('l j F Y') }}</span>
                    <div class="flex items-center rounded-lg border border-gray-200 dark:border-gray-700">
                        <button
                            wire:click="switchTab('list')"
                            @class([
                                'px-3 py-1.5 text-xs font-medium rounded-l-lg transition-colors',
                                'bg-primary-500 text-black' => $this->activeTab === 'list',
                                'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' => $this->activeTab !== 'list',
                            ])
                        >
                            {{ __('List') }}
                        </button>
                        <button
                            wire:click="switchTab('calendar')"
                            @class([
                                'px-3 py-1.5 text-xs font-medium rounded-r-lg transition-colors',
                                'bg-primary-500 text-black' => $this->activeTab === 'calendar',
                                'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' => $this->activeTab !== 'calendar',
                            ])
                        >
                            {{ __('Calendar') }}
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::button size="sm" color="gray" wire:click="today">
                        {{ __('today') }}
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" wire:click="previousDay" />
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" wire:click="nextDay" />
                </div>
            </div>
        </x-slot>

        {{-- List view --}}
        @if($this->activeTab === 'list')
            @if(empty($this->events))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No events scheduled for this day.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('Time') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('Course') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('Teacher') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('Room') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($this->events as $event)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $event['start'] }} – {{ $event['end'] }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $event['color'] }}"></span>
                                            <span class="text-gray-900 dark:text-gray-100">{{ $event['title'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $event['teacher'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $event['room'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- Calendar view --}}
        @if($this->activeTab === 'calendar')
            @if(empty($this->calendarEvents))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No events scheduled for this day.') }}</p>
            @else
                <div
                    wire:ignore
                    x-data="dailyOverviewCalendar({
                        events: @js($this->calendarEvents),
                        resources: @js($this->resources),
                        initialDate: @js($this->selectedDate),
                        locale: '{{ app()->getLocale() }}',
                        slotMinTime: '{{ config('campusflow.calendar_start', '06:00:00') }}',
                    })"
                    x-init="init()"
                    style="min-height: 500px;"
                ></div>
            @endif
        @endif
    </x-filament::section>

    @if($this->activeTab === 'calendar')
        @script
        <script>
            Alpine.data('dailyOverviewCalendar', (config) => ({
                calendar: null,

                init() {
                    this.loadFullCalendar().then(() => {
                        this.renderCalendar();

                        Livewire.on('dailyOverviewEventsUpdated', ({ events, resources, date }) => {
                            if (this.calendar) {
                                this.calendar.destroy();
                            }
                            config.events = events;
                            config.resources = resources;
                            config.initialDate = date;
                            this.renderCalendar();
                        });
                    });
                },

                renderCalendar() {
                    this.calendar = new FullCalendar.Calendar(this.$el, {
                        initialView: 'resourceTimeGridDay',
                        initialDate: config.initialDate,
                        schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
                        resources: config.resources,
                        events: config.events,
                        locale: config.locale,
                        slotMinTime: config.slotMinTime,
                        slotMaxTime: '22:00:00',
                        allDaySlot: false,
                        nowIndicator: true,
                        height: 'auto',
                        headerToolbar: false,
                        eventDidMount(info) {
                            const room = info.event.extendedProps.room;
                            if (room) {
                                info.el.title = info.event.title + '\n' + room;
                            }
                        },
                    });
                    this.calendar.render();
                },

                loadFullCalendar() {
                    const loadScript = (src) => new Promise((resolve) => {
                        if (document.querySelector(`script[src='${src}']`)) { resolve(); return; }
                        const s = document.createElement('script');
                        s.src = src;
                        s.onload = resolve;
                        document.head.appendChild(s);
                    });

                    const loadStyle = (href) => {
                        if (document.querySelector(`link[href='${href}']`)) return Promise.resolve();
                        const l = document.createElement('link');
                        l.rel = 'stylesheet';
                        l.href = href;
                        document.head.appendChild(l);
                        return Promise.resolve();
                    };

                    return Promise.all([
                        loadStyle('https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.css'),
                        loadScript('https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.js'),
                    ]);
                },
            }));
        </script>
        @endscript
    @endif
</x-filament-widgets::widget>
