<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\AdmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The admission of a student to a section of a class at a branch for an academic year.
 */
class Admission extends Model
{
    /** @use HasFactory<AdmissionFactory> */
    use BelongsToBranch, HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (Admission $admission): void {
            $admission->admission_number ??= static::nextAdmissionNumber($admission->campus_id, $admission->year_id);
        });
    }

    /**
     * The number is made of the branch code, the start of the academic year and a sequence: MAIN-2026-0001.
     */
    public static function nextAdmissionNumber(int $campusId, int $yearId): string
    {
        $prefix = (Campus::query()->find($campusId)?->code ?? 'ADM').'-'.(Year::query()->find($yearId)?->start?->year ?? now()->year);
        $sequence = static::query()->where('campus_id', $campusId)->where('year_id', $yearId)->count() + 1;

        while (static::query()->where('admission_number', $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT))->exists()) {
            $sequence++;
        }

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SubjectResult::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ClassAttendance::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(FeeVoucher::class);
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(FeeDiscount::class, 'admission_fee_discount')->withTimestamps();
    }

    public function disciplineRecords(): HasMany
    {
        return $this->hasMany(DisciplineRecord::class);
    }

    public function assessmentMarks(): HasMany
    {
        return $this->hasMany(AssessmentMark::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AdmissionStatus::Active);
    }

    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'admitted_on' => 'date',
        ];
    }
}
