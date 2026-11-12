<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClassAttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::Absent => __('Absent'),
            self::Late => __('Late'),
            self::Leave => __('Leave'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Leave => 'info',
        };
    }

    /**
     * Whether the student attended the school that day.
     */
    public function isAttended(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
