<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where an admission enquiry stands. Before registration the front desk follows up on it (in process, on hold,
 * interested, not interested); once registered, it goes through the admission steps (EnquiryStage) until the
 * student is admitted.
 */
enum EnquiryStatus: string implements HasColor, HasLabel
{
    case InProcess = 'in_process';
    case Hold = 'hold';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case Registered = 'registered';
    case Admitted = 'admitted';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProcess => __('In process'),
            self::Hold => __('On hold'),
            self::Interested => __('Interested'),
            self::NotInterested => __('Not interested'),
            self::Registered => __('Registered'),
            self::Admitted => __('Admitted'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProcess => 'info',
            self::Hold => 'gray',
            self::Interested => 'warning',
            self::NotInterested => 'danger',
            self::Registered => 'primary',
            self::Admitted => 'success',
        };
    }

    /**
     * Whether the enquiry is still followed up before registration.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::InProcess, self::Hold, self::Interested], true);
    }

    /**
     * The statuses the front desk sets by hand while following up. Registered and Admitted are set by the funnel.
     *
     * @return array<string, string>
     */
    public static function followUpOptions(): array
    {
        return collect([self::InProcess, self::Hold, self::Interested, self::NotInterested])
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->getLabel()])
            ->all();
    }
}
