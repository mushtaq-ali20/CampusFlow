<?php

namespace App\Models;

use App\Enums\ApplicantStage;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\JobApplicantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A person who applied for a job opening. The front desk enters the application and the CV; the applicant is then
 * shortlisted, interviewed, offered the job and hired, or rejected along the way.
 */
class JobApplicant extends Model implements HasMedia
{
    /** @use HasFactory<JobApplicantFactory> */
    use BelongsToBranch, HasFactory, InteractsWithMedia, LogsActivity;

    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logUnguarded();
    }

    protected static function booted(): void
    {
        static::saving(function (self $applicant): void {
            if ($applicant->campus_id === null && $applicant->job_opening_id !== null) {
                $applicant->campus_id = JobOpening::query()->whereKey($applicant->job_opening_id)->value('campus_id');
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv')->singleFile()->useDisk('local');
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function getNameAttribute(): string
    {
        return Str::title($this->firstname).' '.Str::upper($this->lastname);
    }

    protected function casts(): array
    {
        return [
            'stage' => ApplicantStage::class,
            'interview_at' => 'datetime',
            'rating' => 'integer',
            'experience_years' => 'integer',
            'expected_salary' => 'integer',
            'offered_salary' => 'integer',
        ];
    }
}
