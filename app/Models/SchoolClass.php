<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A class (grade) of the school, such as Pre Nursery or Class 1. The order is the promotion order.
 */
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class)
            ->withPivot(['total_marks', 'passing_marks'])
            ->withTimestamps()
            ->orderBy('name');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * The class students are promoted to, or null for the last class.
     */
    public function nextClass(): ?self
    {
        return static::query()
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('order');
    }

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }
}
