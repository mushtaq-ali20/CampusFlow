<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who raised a complaint.
 */
enum ComplainantType: string implements HasLabel
{
    case Parent = 'parent';
    case Student = 'student';
    case Staff = 'staff';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Parent => __('Parent'),
            self::Student => __('Student'),
            self::Staff => __('Staff'),
            self::Other => __('Other'),
        };
    }
}
