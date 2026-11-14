{{--
    The card at the top of a record's view page: its name, a status badge and one line of facts.
--}}
<div class="flex flex-wrap items-center gap-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2.5">
            <h2 class="m-0 text-2xl font-bold text-gray-900 dark:text-white">{{ $header['title'] }}</h2>
            @if ($header['badge'] ?? null)
                <x-filament::badge :color="$header['badge']['color']">{{ $header['badge']['label'] }}</x-filament::badge>
            @endif
        </div>
        @if (filled($header['subtitle'] ?? null))
            <p class="mt-1 text-[13.5px] text-gray-600 dark:text-gray-400">{{ $header['subtitle'] }}</p>
        @endif
    </div>
</div>
