<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmployeeStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Working'),
            self::Left => __('Left'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Left => 'gray',
        };
    }
}
