<?php

namespace App\Models;

use Database\Factories\TeachingAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The teacher who teaches a subject to a section. The teacher then works at the branch of the section.
 */
class TeachingAssignment extends Model
{
    /** @use HasFactory<TeachingAssignmentFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(function (TeachingAssignment $assignment): void {
            $assignment->teacher?->campuses()->syncWithoutDetaching([$assignment->section->campus_id]);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
