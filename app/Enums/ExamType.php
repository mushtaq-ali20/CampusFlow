<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The three exams of the academic year. A student is promoted to the next class by passing the Annual exam.
 */
enum ExamType: string implements HasLabel
{
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Annual = 'annual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Quarterly => __('Quarterly'),
            self::HalfYearly => __('Half Yearly'),
            self::Annual => __('Annual'),
        };
    }
}
