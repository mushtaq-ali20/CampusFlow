<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Finalized => __('Finalized'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Finalized => 'success',
        };
    }
}
