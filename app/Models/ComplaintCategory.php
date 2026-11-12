<?php

namespace App\Models;

use Database\Factories\ComplaintCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A kind of complaint, such as Transport or Fees: its time limit in hours, who handles a new complaint, and to whom
 * it goes when the time limit passes. Categories are deactivated, not deleted, because complaints refer to them.
 */
class ComplaintCategory extends Model
{
    /** @use HasFactory<ComplaintCategoryFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function assignTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to_id');
    }

    public function escalateTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'response_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
