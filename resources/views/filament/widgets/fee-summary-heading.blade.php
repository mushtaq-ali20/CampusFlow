<div class="flex flex-wrap items-center gap-3">
    <span>{{ __('Fee summary') }}</span>
    <label class="flex items-center gap-2 text-sm font-normal">
        <span class="text-gray-500 dark:text-gray-400">{{ __('Group by') }}</span>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="level">
                @foreach ($options as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </label>
</div>
