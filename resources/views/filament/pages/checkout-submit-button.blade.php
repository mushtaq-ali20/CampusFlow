<x-filament::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="submit">
    <span wire:loading.remove wire:target="submit">{{ __('Create Invoice') }}</span>
    <span wire:loading wire:target="submit">{{ __('Processing...') }}</span>
</x-filament::button>
