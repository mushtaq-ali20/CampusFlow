<?php

namespace App\Models;

use App\Enums\Shift;
use Database\Factories\TimetablePeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A period of the school day, such as "Period 1, 08:00 - 08:40", or a break. The periods are configurable.
 */
class TimetablePeriod extends Model
{
    /** @use HasFactory<TimetablePeriodFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('order')->orderBy('start_time');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForShift(Builder $query, Shift $shift): void
    {
        $query->where('shift', $shift);
    }

    /**
     * The start and end of the period, such as "08:00 - 08:40".
     */
    public function timeRange(): string
    {
        return substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5);
    }

    protected function casts(): array
    {
        return [
            'shift' => Shift::class,
            'is_break' => 'boolean',
            'order' => 'integer',
        ];
    }
}
