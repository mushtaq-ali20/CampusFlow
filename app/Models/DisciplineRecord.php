<?php

namespace App\Models;

use App\Enums\DisciplineConsequence;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\DisciplineRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A merit or a violation recorded for a student. The branch, the section, the points and the consequence are
 * copied from the admission and the category when the record is saved.
 */
class DisciplineRecord extends Model
{
    /** @use HasFactory<DisciplineRecordFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (DisciplineRecord $record): void {
            if ($record->isDirty('admission_id') || ! $record->exists) {
                $admission = $record->admission;
                $record->campus_id = $admission->campus_id;
                $record->section_id = $admission->section_id;
            }

            if ($record->isDirty('discipline_category_id') || ! $record->exists) {
                $category = $record->category;
                $record->points = $category->signedPoints();
                $record->consequence = $category->consequence;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DisciplineCategory::class, 'discipline_category_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The records of the sections a teacher teaches, as class teacher or for a subject.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfSectionsTaughtBy(Builder $query, User $user): void
    {
        $query->whereIn('section_id', Section::query()->taughtBy($user)->select('id'));
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'points' => 'integer',
            'consequence' => DisciplineConsequence::class,
        ];
    }
}
