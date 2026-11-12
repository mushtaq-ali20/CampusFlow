<?php

namespace App\Models;

use App\Enums\Shift;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A section of a class at a branch for an academic year, such as Class 1 - A.
 */
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
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

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function classAttendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class);
    }

    /**
     * The sections a teacher teaches: as their class teacher, or for a subject.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTaughtBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $sections) => $sections
            ->where('class_teacher_id', $user->id)
            ->orWhereHas('teachingAssignments', fn (Builder $assignments) => $assignments->where('teacher_id', $user->id)));
    }

    /**
     * The full name of the section with its shift, such as "Class 1 - A (Morning)", since a class can have
     * sections with the same name in both shifts.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => $this->schoolClass?->name.' - '.$this->name.($this->shift ? ' ('.$this->shift->getLabel().')' : ''));
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'shift' => Shift::class,
        ];
    }
}
