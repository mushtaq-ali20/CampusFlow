<?php

namespace App\Models;

use App\Enums\FeeFrequency;
use Database\Factories\FeeHeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of fee charged to the students, such as Tuition (monthly), Admission fee (one-time) or Annual fee.
 */
class FeeHead extends Model
{
    /** @use HasFactory<FeeHeadFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    protected function casts(): array
    {
        return [
            'frequency' => FeeFrequency::class,
            'is_active' => 'boolean',
        ];
    }
}
