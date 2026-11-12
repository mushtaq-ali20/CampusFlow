<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A line of a salary: money added to it (an allowance) or taken from it (a fixed deduction).
 */
enum PayItemKind: string implements HasColor, HasLabel
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Earning => __('Allowance'),
            self::Deduction => __('Deduction'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Earning => 'success',
            self::Deduction => 'danger',
        };
    }
}
