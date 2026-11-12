<?php

namespace App\Models;

use App\Enums\DisciplineConsequence;
use App\Enums\DisciplineKind;
use Database\Factories\DisciplineCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A kind of good conduct (a merit) or misconduct (a violation), with its points and, for a violation, what follows.
 * Categories are deactivated, not deleted, because the records refer to them.
 */
class DisciplineCategory extends Model
{
    /** @use HasFactory<DisciplineCategoryFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function records(): HasMany
    {
        return $this->hasMany(DisciplineRecord::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The points a record of this category adds to the student's total: positive for a merit, negative for a violation.
     */
    public function signedPoints(): int
    {
        return $this->kind->signedPoints($this->points);
    }

    protected function casts(): array
    {
        return [
            'kind' => DisciplineKind::class,
            'consequence' => DisciplineConsequence::class,
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
