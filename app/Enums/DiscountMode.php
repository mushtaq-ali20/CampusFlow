<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountMode: string implements HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => __('Percentage'),
            self::Fixed => __('Fixed amount'),
        };
    }
}
