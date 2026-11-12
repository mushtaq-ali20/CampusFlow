<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeeFrequency: string implements HasLabel
{
    case Monthly = 'monthly';
    case OneTime = 'one_time';
    case Annual = 'annual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::OneTime => __('One-time (at admission)'),
            self::Annual => __('Annual'),
        };
    }
}
