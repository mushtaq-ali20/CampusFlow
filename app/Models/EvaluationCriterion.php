<?php

namespace App\Models;

use Database\Factories\EvaluationCriterionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something the employees are evaluated on, such as punctuality, with the weight it has in the overall score.
 */
class EvaluationCriterion extends Model
{
    /** @use HasFactory<EvaluationCriterionFactory> */
    use HasFactory;

    protected $table = 'evaluation_criteria';

    protected $guarded = ['id'];

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
