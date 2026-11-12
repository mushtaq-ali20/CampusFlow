<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The score of an evaluation on one criterion, from 1 to 5, with the weight the criterion had at that time.
 */
class AppraisalScore extends Model
{
    protected $guarded = ['id'];

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'weight' => 'integer',
        ];
    }
}
