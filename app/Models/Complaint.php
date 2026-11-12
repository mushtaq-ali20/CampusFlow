<?php

namespace App\Models;

use App\Enums\ComplainantType;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A complaint from a parent, a student or a member of staff. See App\Services\ComplaintService for the time limit,
 * the assignment and the escalation.
 */
class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (Complaint $complaint): void {
            $complaint->complaint_number ??= static::nextComplaintNumber();
        });
    }

    /**
     * The number is made of the year and a sequence: C-2026-0001.
     */
    public static function nextComplaintNumber(): string
    {
        $prefix = 'C-'.now()->year;
        $sequence = static::query()->where('complaint_number', 'like', $prefix.'-%')->count() + 1;

        while (static::query()->where('complaint_number', $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT))->exists()) {
            $sequence++;
        }

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class);
    }

    /**
     * The complaints that still wait for a resolution.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', ComplaintStatus::Resolved);
    }

    /**
     * Whether the complaint is still open after its time limit.
     */
    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'complainant_type' => ComplainantType::class,
            'priority' => ComplaintPriority::class,
            'status' => ComplaintStatus::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalation_level' => 'integer',
        ];
    }
}
