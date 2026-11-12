<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Shift: string implements HasLabel
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => __('Morning'),
            self::Afternoon => __('Afternoon'),
        };
    }
}
