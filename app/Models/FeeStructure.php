<?php

namespace App\Models;

use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The amount of a fee head for a class in an academic year. Without a branch the amount applies to all
 * branches; a structure with a branch overrides it for that branch.
 */
class FeeStructure extends Model
{
    /** @use HasFactory<FeeStructureFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }
}
