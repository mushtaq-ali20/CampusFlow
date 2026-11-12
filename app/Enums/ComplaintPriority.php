<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The priority shortens or lengthens the time limit of the complaint's category.
 */
enum ComplaintPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
            self::Urgent => __('Urgent'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    /**
     * The time limit for this priority, from the time limit of the category: twice as long for a low priority,
     * half for a high one and a quarter for an urgent one, but never less than an hour.
     */
    public function hoursFor(int $categoryHours): int
    {
        $hours = match ($this) {
            self::Low => $categoryHours * 2,
            self::Medium => $categoryHours,
            self::High => intdiv($categoryHours, 2),
            self::Urgent => intdiv($categoryHours, 4),
        };

        return max(1, $hours);
    }
}
