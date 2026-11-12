<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a job applicant stands, from the application to being hired or rejected.
 */
enum ApplicantStage: string implements HasColor, HasLabel
{
    case Applied = 'applied';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offered = 'offered';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => __('Applied'),
            self::Shortlisted => __('Shortlisted'),
            self::Interview => __('Interview'),
            self::Offered => __('Offered'),
            self::Hired => __('Hired'),
            self::Rejected => __('Rejected'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Applied => 'gray',
            self::Shortlisted => 'info',
            self::Interview => 'warning',
            self::Offered => 'primary',
            self::Hired => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * Whether the applicant is still being considered.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Hired, self::Rejected], true);
    }
}
