<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ComplaintStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Escalated = 'escalated';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::InProgress => __('In progress'),
            self::Escalated => __('Escalated'),
            self::Resolved => __('Resolved'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::InProgress => 'info',
            self::Escalated => 'danger',
            self::Resolved => 'success',
        };
    }

    /**
     * Whether the complaint still waits for a resolution.
     */
    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
