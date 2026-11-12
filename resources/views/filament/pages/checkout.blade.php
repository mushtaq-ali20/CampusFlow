<x-filament-panels::page>
    <div class="relative">
        <div wire:loading wire:target="submit"
             class="absolute inset-0 z-50 flex items-center justify-center bg-white/75 dark:bg-gray-900/75 rounded-xl">
            <x-filament::loading-indicator class="h-8 w-8" />
        </div>
        <form wire:submit="submit">
            {{ $this->form }}
        </form>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
