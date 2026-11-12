<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PtmMeetingStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Invited = 'invited';
    case Confirmed = 'confirmed';
    case Met = 'met';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Invited => __('Invited'),
            self::Confirmed => __('Confirmed'),
            self::Met => __('Met'),
            self::NoShow => __('Did not come'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Invited => 'info',
            self::Confirmed => 'primary',
            self::Met => 'success',
            self::NoShow => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
