<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How a family pays its fees, from the vouchers due in the last six months (see FeeLedgerService::paymentHabit).
 */
enum PaymentHabit: string implements HasColor, HasLabel
{
    case OnTime = 'on_time';
    case SometimesLate = 'sometimes_late';
    case OftenLate = 'often_late';
    case Defaulter = 'defaulter';
    case New = 'new';

    public function getLabel(): string
    {
        return match ($this) {
            self::OnTime => __('Pays on time'),
            self::SometimesLate => __('Sometimes late'),
            self::OftenLate => __('Often late'),
            self::Defaulter => __('Defaulter'),
            self::New => __('No history yet'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OnTime => 'success',
            self::SometimesLate => 'info',
            self::OftenLate => 'warning',
            self::Defaulter => 'danger',
            self::New => 'gray',
        };
    }
}
