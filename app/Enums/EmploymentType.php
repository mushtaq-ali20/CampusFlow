<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an employee is engaged by the school.
 */
enum EmploymentType: string implements HasLabel
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case PartTime = 'part_time';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => __('Permanent'),
            self::Contract => __('Contract'),
            self::PartTime => __('Part time'),
        };
    }
}
