<?php

namespace App\Models;

use Database\Factories\AppraisalCycleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round of evaluations, such as "Annual 2026". Once it is closed its evaluations no longer change.
 */
class AppraisalCycle extends Model
{
    /** @use HasFactory<AppraisalCycleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'is_closed' => 'boolean',
        ];
    }
}
