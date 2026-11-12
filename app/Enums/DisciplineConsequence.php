<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What follows a violation of a category: it is only noted, a warning is sent to the parents, or the parents are
 * called in to the school.
 */
enum DisciplineConsequence: string implements HasColor, HasLabel
{
    case Noted = 'noted';
    case WarningToParent = 'warning_to_parent';
    case ParentCalledIn = 'parent_called_in';

    public function getLabel(): string
    {
        return match ($this) {
            self::Noted => __('Noted'),
            self::WarningToParent => __('Warning to parent'),
            self::ParentCalledIn => __('Parent called in'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Noted => 'gray',
            self::WarningToParent => 'warning',
            self::ParentCalledIn => 'danger',
        };
    }

    /**
     * Whether the parents should be told about the record.
     */
    public function informsParent(): bool
    {
        return $this !== self::Noted;
    }
}
