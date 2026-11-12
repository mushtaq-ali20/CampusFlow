<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\PtmEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A parent teacher meeting day at a branch for some sections. Each student gets a time slot with their class teacher.
 */
class PtmEvent extends Model
{
    /** @use HasFactory<PtmEventFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'ptm_event_section');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(PtmMeeting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'slot_minutes' => 'integer',
        ];
    }
}
