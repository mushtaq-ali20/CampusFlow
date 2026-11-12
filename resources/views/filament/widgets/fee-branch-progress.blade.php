<x-filament-widgets::widget>
    <x-filament::section :heading="__('Collected by branch')" icon="heroicon-o-building-library">
        @if ($branches->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No fee vouchers for these filters.') }}</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($branches as $branch)
                    @php($rate = min(100, (float) ($branch->rate ?? 0)))
                    <li class="grid grid-cols-1 items-center gap-x-4 gap-y-1 text-sm sm:grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)_auto]">
                        <span class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
                            <svg viewBox="0 0 24 24" class="size-5 shrink-0" aria-hidden="true">
                                <rect x="5" y="9" width="14" height="12" fill="#fcd88a" />
                                <polygon points="3,10 12,3 21,10" fill="#e2553f" />
                                <rect x="10" y="14" width="4" height="7" fill="#7c2d12" />
                            </svg>
                            {{ $branch->campus?->name }}
                        </span>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $branch->campus?->name }}">
                            <div class="art-grow h-full rounded-full bg-amber-500" style="width: {{ $rate }}%; animation-delay: {{ $loop->index * 120 }}ms"></div>
                        </div>
                        <span class="whitespace-nowrap text-gray-600 dark:text-gray-400">
                            <b class="text-gray-950 dark:text-white">{{ \App\Services\FeeService::money((int) $branch->collected) }}</b>
                            {{ __('of :billed', ['billed' => \App\Services\FeeService::money((int) $branch->billed)]) }} · {{ $rate }}%
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
