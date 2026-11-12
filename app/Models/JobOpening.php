<?php

namespace App\Models;

use App\Enums\ApplicantStage;
use App\Enums\EmploymentType;
use App\Enums\OpeningStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\JobOpeningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A vacancy of a branch, with the number of people wanted.
 */
class JobOpening extends Model
{
    /** @use HasFactory<JobOpeningFactory> */
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

    public function applicants(): HasMany
    {
        return $this->hasMany(JobApplicant::class);
    }

    public function hiredCount(): int
    {
        return $this->applicants()->where('stage', ApplicantStage::Hired)->count();
    }

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => OpeningStatus::class,
            'positions' => 'integer',
            'closes_on' => 'date',
        ];
    }
}
