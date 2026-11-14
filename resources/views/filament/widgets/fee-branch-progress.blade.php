<x-filament-widgets::widget>
    <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 dark:border-white/5">
        <h3 class="text-xs font-semibold text-gray-600 dark:text-gray-400">{{ __('Collected by branch') }}</h3>

        @if ($branches->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No fee vouchers for these filters.') }}</p>
        @else
            <ul class="flex flex-col gap-4">
                @foreach ($branches as $branch)
                    @php($rate = min(100, (float) ($branch->rate ?? 0)))
                    <li class="flex flex-col gap-1.5 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $branch->campus?->name }}</span>
                            <span class="text-gray-600 tabular-nums dark:text-gray-400">
                                <b class="text-gray-950 dark:text-white">{{ \App\Services\FeeService::money((int) $branch->collected) }}</b>
                                {{ __('of :billed', ['billed' => \App\Services\FeeService::money((int) $branch->billed)]) }} · {{ $rate }}%
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $branch->campus?->name }}">
                            <div class="cf-grow h-full rounded-full bg-(--primary-600) dark:bg-(--primary-400)" style="width: {{ $rate }}%; animation-delay: {{ $loop->index * 120 }}ms"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-widgets::widget>
