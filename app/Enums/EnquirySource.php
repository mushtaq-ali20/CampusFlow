<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the family heard of the school.
 */
enum EnquirySource: string implements HasLabel
{
    case WalkIn = 'walk_in';
    case Website = 'website';
    case SocialMedia = 'social_media';
    case Reference = 'reference';
    case Sibling = 'sibling';
    case Newspaper = 'newspaper';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WalkIn => __('Walk-in'),
            self::Website => __('Website'),
            self::SocialMedia => __('Social media'),
            self::Reference => __('Personal reference'),
            self::Sibling => __('Sibling at school'),
            self::Newspaper => __('Newspaper'),
            self::Other => __('Other'),
        };
    }
}
