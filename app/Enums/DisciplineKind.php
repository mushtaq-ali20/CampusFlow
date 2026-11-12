<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A discipline category rewards good conduct (merit points) or records misconduct (points taken away).
 */
enum DisciplineKind: string implements HasColor, HasLabel
{
    case Merit = 'merit';
    case Violation = 'violation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Merit => __('Merit'),
            self::Violation => __('Violation'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Merit => 'success',
            self::Violation => 'danger',
        };
    }

    /**
     * The points a record of this kind adds to the student's total: positive for a merit, negative for a violation.
     */
    public function signedPoints(int $points): int
    {
        return $this === self::Merit ? abs($points) : -abs($points);
    }
}
