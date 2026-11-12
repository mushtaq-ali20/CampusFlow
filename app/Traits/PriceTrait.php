<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait PriceTrait
{
    public function price(): Attribute
    {
        return new Attribute(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    public function getPriceWithCurrencyAttribute(): string
    {
        if (config('campusflow.currency_position') === 'before') {
            return config('campusflow.currency_symbol').' '.$this->price;
        }

        return $this->price.' '.config('campusflow.currency_symbol');
    }
}
