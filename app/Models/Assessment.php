<?php

namespace App\Models;

use App\Enums\AssessmentType;
use App\Enums\ExamType;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A class assessment of a subject in a section, such as a class test or an observation, counting towards an exam
 * term. The branch is copied from the section when it is saved.
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Assessment $assessment): void {
            if ($assessment->isDirty('section_id') || ! $assessment->exists) {
                $assessment->campus_id = Section::query()->findOrFail($assessment->section_id)->campus_id;
            }
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

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(AssessmentMark::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'exam' => ExamType::class,
            'type' => AssessmentType::class,
            'held_on' => 'date',
            'total_marks' => 'integer',
        ];
    }
}
