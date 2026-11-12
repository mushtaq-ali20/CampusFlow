<?php

namespace App\Models;

use Database\Factories\AssessmentMarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The marks of a student in a class assessment.
 */
class AssessmentMark extends Model
{
    /** @use HasFactory<AssessmentMarkFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    protected function casts(): array
    {
        return [
            'marks' => 'float',
        ];
    }
}
