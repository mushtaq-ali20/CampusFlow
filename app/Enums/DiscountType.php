<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Sibling = 'sibling';
    case Scholarship = 'scholarship';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sibling => __('Sibling discount'),
            self::Scholarship => __('Scholarship'),
            self::Other => __('Other'),
        };
    }
}
