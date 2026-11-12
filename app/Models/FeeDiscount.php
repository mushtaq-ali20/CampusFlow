<?php

namespace App\Models;

use App\Enums\DiscountMode;
use App\Enums\DiscountType;
use Database\Factories\FeeDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A sibling discount, scholarship or other discount given to admitted students. Without a fee head it
 * applies to the monthly fees; with a fee head it applies to that fee only.
 */
class FeeDiscount extends Model
{
    /** @use HasFactory<FeeDiscountFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function admissions(): BelongsToMany
    {
        return $this->belongsToMany(Admission::class, 'admission_fee_discount')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'mode' => DiscountMode::class,
            'value' => 'integer',
        ];
    }
}
