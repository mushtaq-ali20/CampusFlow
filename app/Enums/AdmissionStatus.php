<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdmissionStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Promoted => __('Promoted'),
            self::Left => __('Left'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Promoted => 'info',
            self::Left => 'gray' ,
        };
    }
}
